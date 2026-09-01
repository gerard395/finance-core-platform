<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransactionReversal;
use App\Domain\Banking\Entities\BankTransactionSettlementReversalLink;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Identity\ValueObjects\UserId;
use DomainException;
use RuntimeException;
use Throwable;

final readonly class ReverseBankTransaction
{
    public function __construct(
        private TransactionManager $transactions,
        private BankTransactionReversalSourceReader $sources,
        private AssessBankTransactionReversalEligibility $eligibility,
        private BankingOpenItemLocker $openItems,
        private JournalEntryStore $journalEntries,
        private OpenItemSettlementStore $settlements,
        private BankTransactionReversalRepository $reversals,
        private BankTransactionSettlementReversalLinkRepository $settlementLinks,
        private BankTransactionReversalIdentityGenerator $identities,
        private BankTransactionClock $clock,
        private AccountingPeriodPostingGuard $periodGuard,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        BankTransactionId $bankTransactionId,
        PostingDate $reversalPostingDate,
        BankTransactionReversalReason $reason,
        UserId $actor,
    ): ReverseBankTransactionResult {
        try {
            return $this->transactions->run(function () use ($administrationId, $bankTransactionId, $reversalPostingDate, $reason, $actor): ReverseBankTransactionResult {
                $source = $this->sources->read($administrationId, $bankTransactionId, true);
                $status = $source === null ? BankTransactionReversalEligibilityStatus::NotFound : $this->eligibility->forSource($source);
                if ($status !== BankTransactionReversalEligibilityStatus::Eligible) {
                    return new ReverseBankTransactionResult($this->status($status));
                }

                $period = $this->periodGuard->lockForPosting($administrationId, $reversalPostingDate);
                if ($period->status !== AccountingPeriodPostingDecisionStatus::Open) {
                    return new ReverseBankTransactionResult(match ($period->status) {
                        AccountingPeriodPostingDecisionStatus::Closed => ReverseBankTransactionStatus::PeriodClosed,
                        AccountingPeriodPostingDecisionStatus::NoPeriod => ReverseBankTransactionStatus::NoAccountingPeriod,
                        AccountingPeriodPostingDecisionStatus::IntegrityFailure => ReverseBankTransactionStatus::PeriodIntegrityFailure,
                        AccountingPeriodPostingDecisionStatus::Open => throw new \LogicException,
                    });
                }

                $openItemIds = [];
                foreach ($source->settlements as $settlement) {
                    $openItemIds[$settlement->openItemId->toString()] = $settlement->openItemId;
                }
                ksort($openItemIds);
                $lockedItems = [];
                foreach ($this->openItems->lock($administrationId, array_values($openItemIds)) as $openItem) {
                    $lockedItems[$openItem->id()->toString()] = $openItem;
                }
                if (count($lockedItems) !== count($openItemIds)) {
                    return new ReverseBankTransactionResult(ReverseBankTransactionStatus::FinancialStateInvalid);
                }

                $source = $this->sources->read($administrationId, $bankTransactionId);
                if ($source === null || $this->eligibility->forSource($source) !== BankTransactionReversalEligibilityStatus::Eligible || $source->posting === null || $source->journalEntry === null) {
                    return new ReverseBankTransactionResult(ReverseBankTransactionStatus::FinancialStateInvalid);
                }

                $reversalId = $this->identities->reversal();
                $reversalJournalEntryId = $this->identities->journalEntry();
                $lines = [];
                foreach ($source->journalEntry->lines() as $originalLine) {
                    $lines[] = new JournalEntryLine(
                        $this->identities->line(),
                        $originalLine->ledgerAccountId(),
                        $originalLine->credit(),
                        $originalLine->debit(),
                        'Reversal '.$originalLine->id()->toString(),
                    );
                }
                $posting = (new PostingEngine(new PostingValidation, static fn () => $reversalJournalEntryId))->post(new PostingRequest(
                    $administrationId,
                    $source->journalEntry->journalId(),
                    $reversalPostingDate,
                    new JournalEntryReference('REV-'.$bankTransactionId->toString()),
                    $lines,
                ));
                $reversalJournalEntry = $posting->journalEntry();
                if (! $posting->isSuccess() || $reversalJournalEntry === null) {
                    throw new RuntimeException('Contra posting validation failed.');
                }
                $this->journalEntries->append($reversalJournalEntry);

                $linkFacts = [];
                foreach ($source->settlements as $settlementSource) {
                    $openItem = $lockedItems[$settlementSource->openItemId->toString()] ?? null;
                    if ($openItem === null || $openItem->settlement($settlementSource->settlement->id()) === null) {
                        throw new DomainException('Original settlement is not part of the locked OpenItem history.');
                    }
                    $settlementReversalId = $this->identities->settlement();
                    $openItem->reverseSettlement($settlementReversalId, $reversalPostingDate, $settlementSource->settlement->id(), $reversalJournalEntryId);
                    $settlementReversal = $openItem->settlement($settlementReversalId);
                    if ($settlementReversal === null) {
                        throw new DomainException('Settlement reversal fact was not created.');
                    }
                    $this->settlements->appendSettlement($openItem, $settlementReversal);
                    $linkFacts[] = new BankTransactionSettlementReversalLink(
                        $this->identities->settlementLink(),
                        $administrationId,
                        $reversalId,
                        $settlementSource->paymentAllocationId,
                        $settlementSource->openItemId,
                        $settlementSource->settlement->id(),
                        $settlementReversalId,
                    );
                }

                $reversedAt = $this->clock->now();
                if (! $this->reversals->appendReversal(new BankTransactionReversal(
                    $reversalId,
                    $administrationId,
                    $bankTransactionId,
                    $source->posting->id,
                    $source->journalEntry->id(),
                    $reversalJournalEntryId,
                    $reversalPostingDate,
                    $reason,
                    $actor,
                    $reversedAt,
                ))) {
                    throw new RuntimeException('Bank transaction reversal fact could not be persisted.');
                }
                foreach ($linkFacts as $linkFact) {
                    if (! $this->settlementLinks->appendLink($linkFact)) {
                        throw new RuntimeException('Bank transaction settlement reversal linkage could not be persisted.');
                    }
                }

                $balances = [];
                foreach ($lockedItems as $openItem) {
                    $balances[] = new ReversedOpenItemBalance($openItem->id(), $openItem->openAmount());
                }

                return new ReverseBankTransactionResult(ReverseBankTransactionStatus::Success, new ReverseBankTransactionSuccess(
                    $reversalId,
                    $bankTransactionId,
                    $source->journalEntry->id(),
                    $reversalJournalEntryId,
                    $reversalPostingDate,
                    count($linkFacts),
                    $balances,
                ));
            });
        } catch (DomainException) {
            return new ReverseBankTransactionResult(ReverseBankTransactionStatus::FinancialStateInvalid);
        } catch (Throwable) {
            return new ReverseBankTransactionResult(ReverseBankTransactionStatus::PostingFailure);
        }
    }

    private function status(BankTransactionReversalEligibilityStatus $status): ReverseBankTransactionStatus
    {
        return match ($status) {
            BankTransactionReversalEligibilityStatus::NotFound => ReverseBankTransactionStatus::NotFound,
            BankTransactionReversalEligibilityStatus::NotPosted => ReverseBankTransactionStatus::NotPosted,
            BankTransactionReversalEligibilityStatus::AlreadyReversed => ReverseBankTransactionStatus::AlreadyReversed,
            BankTransactionReversalEligibilityStatus::FinancialStateInvalid => ReverseBankTransactionStatus::FinancialStateInvalid,
            BankTransactionReversalEligibilityStatus::Eligible => throw new RuntimeException('Eligible status must continue reversal execution.'),
        };
    }
}

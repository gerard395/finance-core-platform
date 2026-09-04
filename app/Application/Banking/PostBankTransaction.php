<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Money;
use Throwable;

final readonly class PostBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $transactionsRepo, private BankTransactionPostingRepository $linkages, private BankingPostingConfigurationReader $configurations, private BankingOpenItemLocker $openItems, private JournalEntryStore $entries, private OpenItemSettlementStore $settlements, private BankPostingIdentityGenerator $ids, private BankTransactionClock $clock, private AccountingPeriodPostingGuard $periodGuard) {}

    public function execute(AdministrationId $admin, BankTransactionId $id, PostingDate $date, UserId $actor): PostBankTransactionStatus
    {
        try {
            return $this->transactions->run(function () use ($admin, $id, $date, $actor) {
                $tx = $this->transactionsRepo->find($admin, $id, true);
                if ($tx === null) {
                    return PostBankTransactionStatus::NotFound;
                }if ($tx->paymentOrNull() === null) {
                    return PostBankTransactionStatus::InvalidState;
                }$linked = $this->linkages->exists($admin, $id);
                if ($tx->status() === BankTransactionStatus::Posted) {
                    return $linked ? PostBankTransactionStatus::AlreadyPosted : PostBankTransactionStatus::FinancialStateInvalid;
                }if ($linked) {
                    return PostBankTransactionStatus::FinancialStateInvalid;
                }if ($tx->status() !== BankTransactionStatus::Finalized) {
                    return PostBankTransactionStatus::InvalidState;
                }$period = $this->periodGuard->lockForPosting($admin, $date);
                if ($period->status !== AccountingPeriodPostingDecisionStatus::Open) {
                    return match ($period->status) {
                        AccountingPeriodPostingDecisionStatus::Closed => PostBankTransactionStatus::PeriodClosed,
                        AccountingPeriodPostingDecisionStatus::NoPeriod => PostBankTransactionStatus::NoAccountingPeriod,
                        AccountingPeriodPostingDecisionStatus::IntegrityFailure => PostBankTransactionStatus::PeriodIntegrityFailure,
                        AccountingPeriodPostingDecisionStatus::Open => throw new \LogicException,
                    };
                }$config = $this->configurations->read($admin, $tx->bankAccountId());
                if ($config->status === BankingPostingConfigurationReadStatus::Missing) {
                    return PostBankTransactionStatus::ConfigurationMissing;
                }if ($config->status === BankingPostingConfigurationReadStatus::InvalidReference || $config->configuration === null) {
                    return PostBankTransactionStatus::ConfigurationInvalid;
                }$allocations = $tx->payment()->allocations();
                $sum = Money::zero($tx->amount()->currency());
                foreach ($allocations as $a) {
                    $sum = $sum->add($a->amount());
                }if ($allocations === [] || ! $sum->equals($tx->amount()->absolute()) || $tx->amount()->currency()->code() !== 'EUR') {
                    return PostBankTransactionStatus::FinancialStateInvalid;
                }$locked = $this->openItems->lock($admin, array_map(static fn ($a) => $a->openItemId(), $allocations));
                $items = [];
                foreach ($locked as $item) {
                    $items[$item->id()->toString()] = $item;
                }if (count($items) !== count($allocations)) {
                    return PostBankTransactionStatus::FinancialStateInvalid;
                }$receipt = $tx->payment()->type() === PaymentType::CustomerReceipt;
                $lines = [];
                $bank = $config->configuration->bankLedgerAccountId;
                $lines[] = new JournalEntryLine($this->ids->line(), $bank, $receipt ? $tx->amount()->absolute() : null, $receipt ? null : $tx->amount()->absolute(), 'Bank '.$tx->reference()->value());
                foreach ($allocations as $a) {
                    $item = $items[$a->openItemId()->toString()];
                    $type = $receipt ? OpenItemType::Receivable : OpenItemType::Payable;
                    $side = $receipt ? OpenItemSide::Debit : OpenItemSide::Credit;
                    if ($a->openItemType() !== $type || $a->openItemSide() !== $side || $a->relationId() === null || ! $a->relationId()->equals($tx->payment()->relationId()) || $item->type() !== $type || $item->side() !== $side || ! $item->relationId()->equals($tx->payment()->relationId()) || $item->originalAmount()->currency()->code() !== 'EUR' || $a->amount()->currency()->code() !== 'EUR') {
                        return PostBankTransactionStatus::FinancialStateInvalid;
                    }if ($item->openAmount()->subtract($a->amount())->isNegative()) {
                        return PostBankTransactionStatus::AllocationExceedsOpenBalance;
                    }if ($a->controlLedgerAccountId() === null || ! $item->controlLedgerAccountId()->equals($a->controlLedgerAccountId())) {
                        return PostBankTransactionStatus::FinancialStateInvalid;
                    }$lines[] = new JournalEntryLine($this->ids->line(), $item->controlLedgerAccountId(), $receipt ? null : $a->amount(), $receipt ? $a->amount() : null, 'Settlement '.$a->id()->toString());
                }$engine = new PostingEngine(new PostingValidation, fn () => $this->ids->journalEntry());
                $posted = $engine->post(new PostingRequest($admin, $config->configuration->bankJournalId, $date, new JournalEntryReference($tx->reference()->value()), $lines));
                $entry = $posted->journalEntry();
                if (! $posted->isSuccess() || $entry === null) {
                    return PostBankTransactionStatus::PostingFailure;
                }$this->entries->append($entry);
                foreach ($allocations as $a) {
                    $item = $items[$a->openItemId()->toString()];
                    $sid = $this->ids->settlement();
                    $item->applySettlement($sid, $date, $a->amount(), $entry->id());
                    $settlement = $item->settlement($sid);
                    if ($settlement === null) {
                        throw new \RuntimeException('Settlement fact missing.');
                    }$this->settlements->appendSettlement($item, $settlement, $a->id());
                }$this->linkages->append($this->ids->posting(), $admin, $id, $entry->id(), $date);
                $tx->markPosted($actor, $this->clock->now());
                $this->transactionsRepo->save($tx);

                return PostBankTransactionStatus::Success;
            });
        } catch (Throwable) {
            return PostBankTransactionStatus::PostingFailure;
        }
    }
}

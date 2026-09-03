<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\OtherBankTransactionIntent;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Money;
use RuntimeException;
use Throwable;

final readonly class CreateAndPostOtherBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $bankTransactions, private AdministrationBankAccountRepository $bankAccounts, private BankingPostingConfigurationReader $configurations, private OtherContraAccountPolicy $contraAccounts, private JournalEntryStore $journals, private BankTransactionPostingRepository $postings, private BankTransactionIdentityGenerator $transactionIds, private BankPostingIdentityGenerator $postingIds, private BankTransactionClock $clock, private AccountingPeriodPostingGuard $periodGuard) {}

    public function execute(AdministrationId $admin, AdministrationBankAccountId $bankAccountId, LedgerAccountId $contraAccountId, Money $signedAmount, PostingDate $postingDate, BankTransactionReference $reference, TransactionDescription $description, UserId $actor, ?BankTransactionId $id = null): PostOtherBankTransactionResult
    {
        if ($signedAmount->isZero() || $signedAmount->currency()->code() !== 'EUR') {
            return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::InvalidAmount);
        }
        try {
            return $this->transactions->run(function () use ($admin, $bankAccountId, $contraAccountId, $signedAmount, $postingDate, $reference, $description, $actor, $id): PostOtherBankTransactionResult {
                $id ??= $this->transactionIds->transaction();
                $bank = $this->bankAccounts->lock($admin, $bankAccountId);
                if ($bank === null || ! $bank->isActive()) {
                    return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::NotFound);
                }
                $existing = $this->bankTransactions->find($admin, $id, true);
                if ($existing !== null) {
                    return new PostOtherBankTransactionResult(
                        $existing->status() === BankTransactionStatus::Posted && $this->postings->exists($admin, $id)
                            ? PostOtherBankTransactionStatus::AlreadyPosted
                            : PostOtherBankTransactionStatus::PostingFailure,
                        $id,
                    );
                }
                $config = $this->configurations->read($admin, $bankAccountId);
                if ($config->status !== BankingPostingConfigurationReadStatus::Success || $config->configuration === null) {
                    return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::MissingPostingConfiguration);
                }
                if (! $this->contraAccounts->isAllowed($admin, $contraAccountId)) {
                    return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::InvalidContraAccount);
                }
                $period = $this->periodGuard->lockForPosting($admin, $postingDate);
                if ($period->status !== AccountingPeriodPostingDecisionStatus::Open) {
                    return new PostOtherBankTransactionResult(match ($period->status) {
                        AccountingPeriodPostingDecisionStatus::Closed => PostOtherBankTransactionStatus::PeriodClosed,
                        AccountingPeriodPostingDecisionStatus::NoPeriod => PostOtherBankTransactionStatus::NoAccountingPeriod,
                        AccountingPeriodPostingDecisionStatus::IntegrityFailure => PostOtherBankTransactionStatus::PeriodIntegrityFailure,
                        AccountingPeriodPostingDecisionStatus::Open => throw new RuntimeException,
                    });
                }
                $now = $this->clock->now();
                $transaction = new BankTransaction($id, $bankAccountId, $admin, new TransactionDate($postingDate->value()), $signedAmount, $reference, $description, new OtherBankTransactionIntent($contraAccountId, $signedAmount->absolute()), BankTransactionStatus::Finalized, $actor, $now, $actor, $now);
                $this->bankTransactions->save($transaction);
                $amount = $signedAmount->absolute();
                $incoming = $signedAmount->isPositive();
                $lines = [
                    new JournalEntryLine($this->postingIds->line(), $config->configuration->bankLedgerAccountId, $incoming ? $amount : null, $incoming ? null : $amount, 'Bank '.$reference->value()),
                    new JournalEntryLine($this->postingIds->line(), $contraAccountId, $incoming ? null : $amount, $incoming ? $amount : null, $description->value()),
                ];
                $posted = (new PostingEngine(new PostingValidation, fn () => $this->postingIds->journalEntry()))->post(new PostingRequest($admin, $config->configuration->bankJournalId, $postingDate, new JournalEntryReference($reference->value()), $lines));
                $entry = $posted->journalEntry();
                if (! $posted->isSuccess() || $entry === null) {
                    throw new RuntimeException('Other posting validation failed.');
                }
                $this->journals->append($entry);
                $this->postings->append($this->postingIds->posting(), $admin, $id, $entry->id(), $postingDate);
                $transaction->markPosted($actor, $this->clock->now());
                $this->bankTransactions->save($transaction);

                return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::Success, $id);
            });
        } catch (Throwable) {
            return new PostOtherBankTransactionResult(PostOtherBankTransactionStatus::PostingFailure);
        }
    }
}

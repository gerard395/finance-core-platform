<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Money;
use RuntimeException;
use Throwable;

final readonly class ReconcileAndPostBankStatementEntry
{
    public function __construct(private TransactionManager $transactions, private BankEntryFinancialReconciliationStore $reconciliations, private BankEntryManualHistoryRepository $manualHistory, private CreateManualBankTransaction $createPayment, private FinalizeBankTransaction $finalizePayment, private PostBankTransaction $postPayment, private CreateAndPostOtherBankTransaction $postOther, private BankTransactionIdentityGenerator $transactionIds, private BankEntryFinancialReconciliationIdentityGenerator $ids, private BankEntryReconciliationClock $clock) {}

    /** @param list<PreparedPaymentAllocation> $allocations */
    public function execute(AdministrationId $administrationId, BankStatementEntryId $entryId, BankEntryReconciliationIntent $intent, PostingDate $postingDate, UserId $actor, array $allocations = [], ?LedgerAccountId $contraAccountId = null): ReconcileBankStatementEntryResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $entryId, $intent, $postingDate, $actor, $allocations, $contraAccountId): ReconcileBankStatementEntryResult {
                $source = $this->reconciliations->lockSource($administrationId, $entryId);
                if ($source === null) {
                    return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::NotFound);
                }
                if ($this->reconciliations->active($administrationId, $entryId) !== null) {
                    return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::AlreadyReconciled);
                }
                if ($this->manualHistory->latest($administrationId, $entryId)?->action === BankEntryManualAction::Ignore) {
                    return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::Ignored);
                }
                if ($source->entry->amount->currency()->code() !== 'EUR' || $source->entry->amount->isZero() || ($source->entry->direction === BankEntryDirection::Credit) !== $source->entry->amount->isPositive()) {
                    return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::FinancialStateInvalid);
                }
                if (($intent === BankEntryReconciliationIntent::CustomerReceipt && $source->entry->direction !== BankEntryDirection::Credit) || ($intent === BankEntryReconciliationIntent::SupplierPayment && $source->entry->direction !== BankEntryDirection::Debit)) {
                    return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::InvalidIntent);
                }

                $transactionId = null;
                if ($intent === BankEntryReconciliationIntent::Other) {
                    if ($contraAccountId === null || $allocations !== []) {
                        return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::InvalidIntent);
                    }
                    $transactionId = $this->transactionIds->transaction();
                    $posted = $this->postOther->execute($administrationId, $source->bankAccountId, $contraAccountId, $source->entry->amount, $postingDate, $this->reference($source), $this->description($source), $actor, $transactionId);
                    if ($posted->status !== PostOtherBankTransactionStatus::Success) {
                        return new ReconcileBankStatementEntryResult($this->otherStatus($posted->status));
                    }
                } else {
                    $validation = $this->validateAllocations($source->entry->amount->absolute(), $allocations);
                    if ($validation !== null) {
                        return new ReconcileBankStatementEntryResult($validation);
                    }
                    $relationId = $allocations[0]->relationId;
                    [$created, $transactionId] = $this->createPayment->execute($administrationId, $source->bankAccountId, new TransactionDate($source->entry->bookingDate), $source->entry->amount, $this->reference($source), $this->description($source), $relationId, $actor, array_map(fn (PreparedPaymentAllocation $allocation): BankTransactionAllocationInput => new BankTransactionAllocationInput($this->transactionIds->allocation(), $allocation->openItemId, $allocation->amount), $allocations));
                    if ($created !== BankTransactionResult::Success || $transactionId === null) {
                        return new ReconcileBankStatementEntryResult($created === BankTransactionResult::InvalidAllocation ? ReconcileBankStatementEntryStatus::InvalidAllocation : ReconcileBankStatementEntryStatus::FinancialStateInvalid);
                    }
                    $finalized = $this->finalizePayment->execute($administrationId, $transactionId, $actor);
                    if ($finalized !== BankTransactionResult::Success) {
                        throw new ReconcileBankStatementEntryFailure($finalized === BankTransactionResult::InvalidAllocation ? ReconcileBankStatementEntryStatus::AllocationExceedsOpenBalance : ReconcileBankStatementEntryStatus::FinancialStateInvalid);
                    }
                    $posted = $this->postPayment->execute($administrationId, $transactionId, $postingDate, $actor);
                    if ($posted !== PostBankTransactionStatus::Success) {
                        throw new ReconcileBankStatementEntryFailure($this->paymentStatus($posted));
                    }
                }

                $id = $this->ids->reconciliationId();
                $reconciliation = new BankEntryReconciliation($id, $administrationId, $entryId, $transactionId, $intent, $source->entry->bookingDate, $postingDate, $actor, $this->clock->now(), $this->reconciliations->latest($administrationId, $entryId)?->id);
                if (! $this->reconciliations->append($reconciliation) || ! $this->reconciliations->activate($reconciliation)) {
                    throw new ReconcileBankStatementEntryFailure(ReconcileBankStatementEntryStatus::ConcurrencyConflict);
                }

                return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::Success, $id, $transactionId);
            });
        } catch (ReconcileBankStatementEntryFailure $failure) {
            return new ReconcileBankStatementEntryResult($failure->status);
        } catch (Throwable) {
            return new ReconcileBankStatementEntryResult(ReconcileBankStatementEntryStatus::ConcurrencyConflict);
        }
    }

    /** @param list<PreparedPaymentAllocation> $allocations */
    private function validateAllocations(Money $expected, array $allocations): ?ReconcileBankStatementEntryStatus
    {
        if ($allocations === []) {
            return ReconcileBankStatementEntryStatus::AllocationIncomplete;
        }
        $relation = $allocations[0]->relationId;
        $sum = Money::zero($expected->currency());
        $ids = [];
        foreach ($allocations as $allocation) {
            if (! $allocation instanceof PreparedPaymentAllocation || ! $allocation->relationId->equals($relation)) {
                return ReconcileBankStatementEntryStatus::RelationMismatch;
            }
            if (isset($ids[$allocation->openItemId->toString()]) || $allocation->amount->isZero() || $allocation->amount->isNegative()) {
                return ReconcileBankStatementEntryStatus::InvalidAllocation;
            }
            $ids[$allocation->openItemId->toString()] = true;
            $sum = $sum->add($allocation->amount);
        }

        return $sum->equals($expected) ? null : ReconcileBankStatementEntryStatus::AllocationIncomplete;
    }

    private function reference(BankEntryPromotionSource $source): BankTransactionReference
    {
        return new BankTransactionReference($source->entry->accountServicerReference ?? $source->entry->entryReference ?? $source->entry->endToEndId ?? $source->entry->id->toString());
    }

    private function description(BankEntryPromotionSource $source): TransactionDescription
    {
        return new TransactionDescription(implode(' | ', array_filter([$source->entry->counterpartyName, ...$source->entry->remittanceLines])) ?: 'Imported bank statement entry');
    }

    private function paymentStatus(PostBankTransactionStatus $status): ReconcileBankStatementEntryStatus
    {
        return match ($status) {
            PostBankTransactionStatus::ConfigurationMissing => ReconcileBankStatementEntryStatus::MissingPostingConfiguration,
            PostBankTransactionStatus::AllocationExceedsOpenBalance => ReconcileBankStatementEntryStatus::AllocationExceedsOpenBalance,
            PostBankTransactionStatus::PeriodClosed => ReconcileBankStatementEntryStatus::PeriodClosed,
            PostBankTransactionStatus::NoAccountingPeriod => ReconcileBankStatementEntryStatus::NoAccountingPeriod,
            PostBankTransactionStatus::PeriodIntegrityFailure => ReconcileBankStatementEntryStatus::PeriodIntegrityFailure,
            PostBankTransactionStatus::PostingFailure => ReconcileBankStatementEntryStatus::PostingFailure,
            default => ReconcileBankStatementEntryStatus::FinancialStateInvalid,
        };
    }

    private function otherStatus(PostOtherBankTransactionStatus $status): ReconcileBankStatementEntryStatus
    {
        return match ($status) {
            PostOtherBankTransactionStatus::InvalidContraAccount => ReconcileBankStatementEntryStatus::InvalidContraAccount,
            PostOtherBankTransactionStatus::MissingPostingConfiguration => ReconcileBankStatementEntryStatus::MissingPostingConfiguration,
            PostOtherBankTransactionStatus::PeriodClosed => ReconcileBankStatementEntryStatus::PeriodClosed,
            PostOtherBankTransactionStatus::NoAccountingPeriod => ReconcileBankStatementEntryStatus::NoAccountingPeriod,
            PostOtherBankTransactionStatus::PeriodIntegrityFailure => ReconcileBankStatementEntryStatus::PeriodIntegrityFailure,
            PostOtherBankTransactionStatus::PostingFailure => ReconcileBankStatementEntryStatus::PostingFailure,
            default => ReconcileBankStatementEntryStatus::FinancialStateInvalid,
        };
    }
}

final class ReconcileBankStatementEntryFailure extends RuntimeException
{
    public function __construct(public readonly ReconcileBankStatementEntryStatus $status)
    {
        parent::__construct($status->value);
    }
}

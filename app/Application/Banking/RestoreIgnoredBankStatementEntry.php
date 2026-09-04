<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\ReconciliationReason;
use App\Domain\Identity\ValueObjects\UserId;
use InvalidArgumentException;
use Throwable;

final readonly class RestoreIgnoredBankStatementEntry
{
    public function __construct(private TransactionManager $transactions, private BankEntryManualHistoryRepository $history, private BankEntryReconciliationIdentityGenerator $ids, private BankEntryReconciliationClock $clock) {}

    public function execute(AdministrationId $administrationId, BankStatementEntryId $entryId, string $reason, UserId $actorId): ManualReconciliationResult
    {
        try {
            $reason = new ReconciliationReason($reason);
        } catch (InvalidArgumentException) {
            return new ManualReconciliationResult(ManualReconciliationStatus::IntegrityFailure);
        }
        try {
            return $this->transactions->run(function () use ($administrationId, $entryId, $reason, $actorId): ManualReconciliationResult {
                if (! $this->history->lockEntry($administrationId, $entryId)) {
                    return new ManualReconciliationResult(ManualReconciliationStatus::NotFound);
                }
                if ($this->history->hasActiveReconciliation($administrationId, $entryId)) {
                    return new ManualReconciliationResult(ManualReconciliationStatus::AlreadyReconciled);
                }
                $latest = $this->history->latest($administrationId, $entryId);
                if ($latest?->action !== BankEntryManualAction::Ignore) {
                    return new ManualReconciliationResult(ManualReconciliationStatus::NotIgnored);
                }
                $id = $this->ids->historyId();
                $event = new BankEntryReconciliationHistory($id, $administrationId, $entryId, BankEntryManualAction::RestoreFromIgnored, $latest->id, $reason, $actorId, $this->clock->now());
                if (! $this->history->append($event)) {
                    return new ManualReconciliationResult(ManualReconciliationStatus::IntegrityFailure);
                }

                return new ManualReconciliationResult(ManualReconciliationStatus::Success, $id);
            });
        } catch (BankEntryManualHistoryIntegrityException) {
            return new ManualReconciliationResult(ManualReconciliationStatus::IntegrityFailure);
        } catch (Throwable) {
            return new ManualReconciliationResult(ManualReconciliationStatus::ConcurrencyConflict);
        }
    }
}

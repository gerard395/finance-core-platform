<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryManualHistoryOrderer;
use App\Application\Banking\BankEntryManualHistoryRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\ReconciliationReason;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentBankEntryManualHistoryRepository implements BankEntryManualHistoryRepository
{
    public function __construct(private readonly BankEntryManualHistoryOrderer $orderer) {}

    public function lockEntry(AdministrationId $administrationId, BankStatementEntryId $entryId): bool
    {
        return DB::table('bank_statement_entries')->where('administration_id', $administrationId->toString())->where('id', $entryId->toString())->lockForUpdate()->exists();
    }

    public function latest(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliationHistory
    {
        $history = $this->history($administrationId, $entryId);

        return $history === [] ? null : $history[array_key_last($history)];
    }

    public function history(AdministrationId $administrationId, BankStatementEntryId $entryId): array
    {
        $history = DB::table('bank_entry_reconciliation_history')->where('administration_id', $administrationId->toString())->where('bank_statement_entry_id', $entryId->toString())->orderBy('sequence')->get()->map(fn (object $row): BankEntryReconciliationHistory => $this->hydrate($row))->all();

        return $this->orderer->order($history);
    }

    public function append(BankEntryReconciliationHistory $history): bool
    {
        $latest = $this->latest($history->administrationId, $history->entryId);
        if ($latest?->id->toString() !== $history->predecessorId?->toString()) {
            return false;
        }
        if (($latest === null && $history->action !== BankEntryManualAction::Ignore)
            || ($latest?->action === BankEntryManualAction::Ignore && $history->action !== BankEntryManualAction::RestoreFromIgnored)
            || ($latest?->action === BankEntryManualAction::RestoreFromIgnored && $history->action !== BankEntryManualAction::Ignore)) {
            return false;
        }
        try {
            return DB::table('bank_entry_reconciliation_history')->insert([
                'id' => $history->id->toString(), 'administration_id' => $history->administrationId->toString(),
                'bank_statement_entry_id' => $history->entryId->toString(), 'action' => $history->action->value,
                'predecessor_id' => $history->predecessorId?->toString(), 'reason' => $history->reason->value,
                'actor_id' => $history->actorId->toString(), 'occurred_at' => $history->occurredAt->format('Y-m-d H:i:s.u'),
            ]);
        } catch (QueryException) {
            return false;
        }
    }

    public function hasActiveReconciliation(AdministrationId $administrationId, BankStatementEntryId $entryId): bool
    {
        return false;
    }

    private function hydrate(object $row): BankEntryReconciliationHistory
    {
        return new BankEntryReconciliationHistory(
            new BankEntryReconciliationHistoryId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)),
            new BankStatementEntryId(new Uuid($row->bank_statement_entry_id)), BankEntryManualAction::from($row->action),
            $row->predecessor_id === null ? null : new BankEntryReconciliationHistoryId(new Uuid($row->predecessor_id)),
            new ReconciliationReason($row->reason), new UserId(new Uuid($row->actor_id)), new DateTimeImmutable($row->occurred_at), (int) $row->sequence,
        );
    }
}

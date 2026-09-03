<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankEntryManualHistoryOrderer;
use App\Application\Banking\BankReconciliationSourceItem;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Banking\ValueObjects\ReconciliationReason;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentBankReconciliationSourceReader implements BankReconciliationSourceReader
{
    public function __construct(private readonly BankEntryManualHistoryOrderer $historyOrderer) {}

    public function list(AdministrationId $administrationId, BankReconciliationWorklistFilter $filter): array
    {
        if (! in_array(BankEntryDerivedState::Unresolved, $filter->states, true) && ! in_array(BankEntryDerivedState::Ignored, $filter->states, true)) {
            return [];
        }
        $latest = DB::table('bank_entry_reconciliation_history')->selectRaw('bank_statement_entry_id, MAX(sequence) AS sequence')->where('administration_id', $administrationId->toString())->groupBy('bank_statement_entry_id');
        $query = DB::table('bank_statement_entries as e')
            ->join('bank_statements as s', fn ($join) => $join->on('s.id', '=', 'e.bank_statement_id')->on('s.administration_id', '=', 'e.administration_id'))
            ->join('bank_import_batches as b', fn ($join) => $join->on('b.id', '=', 's.bank_import_batch_id')->on('b.administration_id', '=', 's.administration_id'))
            ->leftJoinSub($latest, 'lh', 'lh.bank_statement_entry_id', '=', 'e.id')
            ->leftJoin('bank_entry_reconciliation_history as h', 'h.sequence', '=', 'lh.sequence')
            ->where('e.administration_id', $administrationId->toString())
            ->select(['e.*', 's.external_id as statement_external_id', 's.bank_import_batch_id', 'h.action as latest_action']);
        $this->filters($query, $filter);
        $rows = $query->orderByDesc('e.booking_date')->orderBy('e.id')->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('id')->all();
        $history = DB::table('bank_entry_reconciliation_history')->where('administration_id', $administrationId->toString())->whereIn('bank_statement_entry_id', $ids)->orderBy('sequence')->get()->groupBy('bank_statement_entry_id');

        return $rows->map(fn (object $row): BankReconciliationSourceItem => new BankReconciliationSourceItem(
            $this->entry($row), new AdministrationBankAccountId(new Uuid($row->administration_bank_account_id)), new BankStatementId(new Uuid($row->bank_statement_id)), new BankImportBatchId(new Uuid($row->bank_import_batch_id)), $row->statement_external_id,
            $row->latest_action === BankEntryManualAction::Ignore->value ? BankEntryDerivedState::Ignored : BankEntryDerivedState::Unresolved,
            $this->historyOrderer->order($history->get($row->id, collect())->map(fn (object $event): BankEntryReconciliationHistory => $this->history($event))->all()),
        ))->all();
    }

    private function filters(Builder $query, BankReconciliationWorklistFilter $filter): void
    {
        if ($filter->bankAccountId !== null) {
            $query->where('e.administration_bank_account_id', $filter->bankAccountId->toString());
        }
        if ($filter->from !== null) {
            $query->whereDate('e.booking_date', '>=', $filter->from->format('Y-m-d'));
        }
        if ($filter->to !== null) {
            $query->whereDate('e.booking_date', '<=', $filter->to->format('Y-m-d'));
        }
        if ($filter->direction !== null) {
            $query->where('e.direction', $filter->direction->value);
        }
        if ($filter->amount !== null) {
            $query->where('e.signed_amount', $filter->amount->amount());
        }
        if ($filter->search !== null && trim($filter->search) !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filter->search)).'%';
            $query->where(static fn (Builder $search): Builder => $search->where('e.account_servicer_reference', 'like', $needle)->orWhere('e.entry_reference', 'like', $needle)->orWhere('e.end_to_end_id', 'like', $needle)->orWhere('e.creditor_reference', 'like', $needle)->orWhere('e.counterparty_name', 'like', $needle)->orWhere('e.remittance_lines', 'like', $needle));
        }
        $states = array_map(static fn (BankEntryDerivedState $state): string => $state->value, $filter->states);
        if (! in_array(BankEntryDerivedState::Ignored->value, $states, true)) {
            $query->where(static fn (Builder $state): Builder => $state->whereNull('h.action')->orWhere('h.action', BankEntryManualAction::RestoreFromIgnored->value));
        } elseif (! in_array(BankEntryDerivedState::Unresolved->value, $states, true)) {
            $query->where('h.action', BankEntryManualAction::Ignore->value);
        }
    }

    private function entry(object $row): BankStatementEntry
    {
        return new BankStatementEntry(new BankStatementEntryId(new Uuid($row->id)), new DateTimeImmutable($row->booking_date), $row->value_date === null ? null : new DateTimeImmutable($row->value_date), new Money($row->signed_amount, new Currency($row->currency)), BankEntryDirection::from($row->direction), (bool) $row->reversal, $row->account_servicer_reference, $row->entry_reference, $row->end_to_end_id, $row->counterparty_name, $row->counterparty_account, json_decode($row->remittance_lines, true, flags: JSON_THROW_ON_ERROR), $row->creditor_reference, $row->mandate_id, $row->bank_transaction_domain, $row->bank_transaction_family, $row->bank_transaction_subfamily, $row->bank_transaction_proprietary_code, json_decode($row->normalized_metadata, true, flags: JSON_THROW_ON_ERROR), (int) $row->source_ordinal);
    }

    private function history(object $row): BankEntryReconciliationHistory
    {
        return new BankEntryReconciliationHistory(new BankEntryReconciliationHistoryId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), new BankStatementEntryId(new Uuid($row->bank_statement_entry_id)), BankEntryManualAction::from($row->action), $row->predecessor_id === null ? null : new BankEntryReconciliationHistoryId(new Uuid($row->predecessor_id)), new ReconciliationReason($row->reason), new UserId(new Uuid($row->actor_id)), new DateTimeImmutable($row->occurred_at), (int) $row->sequence);
    }
}

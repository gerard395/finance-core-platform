<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankEntryFinancialAllocationSummary;
use App\Application\Banking\BankEntryFinancialHistoryOrderer;
use App\Application\Banking\BankEntryFinancialSummary;
use App\Application\Banking\BankEntryManualHistoryOrderer;
use App\Application\Banking\BankReconciliationSourceItem;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
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
    public function __construct(private readonly BankEntryManualHistoryOrderer $historyOrderer, private readonly BankEntryFinancialHistoryOrderer $financialHistoryOrderer) {}

    public function list(AdministrationId $administrationId, BankReconciliationWorklistFilter $filter): array
    {
        $latest = DB::table('bank_entry_reconciliation_history')->selectRaw('bank_statement_entry_id, MAX(sequence) AS sequence')->where('administration_id', $administrationId->toString())->groupBy('bank_statement_entry_id');
        $query = DB::table('bank_statement_entries as e')
            ->join('bank_statements as s', fn ($join) => $join->on('s.id', '=', 'e.bank_statement_id')->on('s.administration_id', '=', 'e.administration_id'))
            ->join('bank_import_batches as b', fn ($join) => $join->on('b.id', '=', 's.bank_import_batch_id')->on('b.administration_id', '=', 's.administration_id'))
            ->leftJoinSub($latest, 'lh', 'lh.bank_statement_entry_id', '=', 'e.id')
            ->leftJoin('bank_entry_reconciliation_history as h', function ($join): void {
                $join->on('h.sequence', '=', 'lh.sequence')
                    ->on('h.bank_statement_entry_id', '=', 'e.id')
                    ->on('h.administration_id', '=', 'e.administration_id');
            })
            ->where('e.administration_id', $administrationId->toString())
            ->select(['e.*', 's.external_id as statement_external_id', 's.bank_import_batch_id', 'h.action as latest_action']);
        $this->filters($query, $filter);
        $rows = $query->orderByDesc('e.booking_date')->orderBy('e.id')->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('id')->all();
        $history = DB::table('bank_entry_reconciliation_history')->where('administration_id', $administrationId->toString())->whereIn('bank_statement_entry_id', $ids)->orderBy('sequence')->get()->groupBy('bank_statement_entry_id');
        $financialRows = DB::table('bank_entry_reconciliations')->where('administration_id', $administrationId->toString())->whereIn('bank_statement_entry_id', $ids)->get();
        $financial = $financialRows->groupBy('bank_statement_entry_id');
        $financialById = $financialRows->keyBy('id');
        $active = DB::table('bank_entry_active_reconciliations')->where('administration_id', $administrationId->toString())->whereIn('bank_statement_entry_id', $ids)->pluck('bank_entry_reconciliation_id', 'bank_statement_entry_id');
        $transactionIds = $financialRows->pluck('bank_transaction_id')->unique()->values()->all();
        $transactions = DB::table('bank_transactions')->where('administration_id', $administrationId->toString())->whereIn('id', $transactionIds)->get()->keyBy('id');
        $postings = DB::table('bank_transaction_postings')->where('administration_id', $administrationId->toString())->whereIn('bank_transaction_id', $transactionIds)->get()->groupBy('bank_transaction_id');
        $journalIds = $postings->flatten()->pluck('journal_entry_id')->unique()->values()->all();
        $journals = DB::table('journal_entries')->where('administration_id', $administrationId->toString())->whereIn('id', $journalIds)->get()->keyBy('id');
        $payments = DB::table('payments')->where('administration_id', $administrationId->toString())->whereIn('bank_transaction_id', $transactionIds)->get()->groupBy('bank_transaction_id');
        $paymentIds = $payments->flatten()->pluck('id')->values()->all();
        $allocations = DB::table('payment_allocations')->where('administration_id', $administrationId->toString())->whereIn('payment_id', $paymentIds)->get()->groupBy('payment_id');
        $allocationIds = $allocations->flatten()->pluck('id')->values()->all();
        $settlements = DB::table('open_item_settlements')->where('administration_id', $administrationId->toString())->whereIn('payment_allocation_id', $allocationIds)->get()->groupBy('payment_allocation_id');
        $other = DB::table('other_bank_transaction_intents')->where('administration_id', $administrationId->toString())->whereIn('bank_transaction_id', $transactionIds)->get()->groupBy('bank_transaction_id');
        $reversals = DB::table('bank_transaction_reversals')->where('administration_id', $administrationId->toString())->whereIn('original_bank_transaction_id', $transactionIds)->get()->groupBy('original_bank_transaction_id');
        $reversalJournalIds = $reversals->flatten()->pluck('reversal_journal_entry_id')->unique()->values()->all();
        $reversalJournals = DB::table('journal_entries')->where('administration_id', $administrationId->toString())->whereIn('id', $reversalJournalIds)->get()->keyBy('id');
        $reversalIds = $reversals->flatten()->pluck('id')->values()->all();
        $reversalLinks = DB::table('bank_transaction_settlement_reversal_links')->where('administration_id', $administrationId->toString())->whereIn('bank_transaction_reversal_id', $reversalIds)->get()->groupBy('bank_transaction_reversal_id');
        $reversalSettlementIds = $reversalLinks->flatten()->pluck('reversal_open_item_settlement_id')->values()->all();
        $reversalSettlements = DB::table('open_item_settlements')->where('administration_id', $administrationId->toString())->whereIn('id', $reversalSettlementIds)->get()->keyBy('id');
        $journalFacts = DB::table('journal_entry_lines')->where('administration_id', $administrationId->toString())->whereIn('journal_entry_id', [...$journalIds, ...$reversalJournalIds])->selectRaw('journal_entry_id, COUNT(*) AS line_count, SUM(COALESCE(debit_amount, 0)) AS debit_total, SUM(COALESCE(credit_amount, 0)) AS credit_total')->groupBy('journal_entry_id')->get()->keyBy('journal_entry_id');

        return $rows->map(function (object $row) use ($history, $financial, $financialById, $active, $transactions, $postings, $journals, $payments, $allocations, $settlements, $other, $reversals, $reversalJournals, $reversalLinks, $reversalSettlements, $journalFacts): BankReconciliationSourceItem {
            $ordered = $this->financialHistoryOrderer->order($financial->get($row->id, collect())->map(fn (object $item) => $this->financialReconciliation($item))->all());
            $latest = $ordered === null || $ordered === [] ? null : $ordered[array_key_last($ordered)];
            $activeRow = $active->has($row->id) ? $financialById->get($active->get($row->id)) : null;
            $reconciliation = $activeRow ?? ($latest === null ? null : $financialById->get($latest->id->toString()));
            [$state, $posting, $reversal] = $this->financialState($row, $ordered, $activeRow, $reconciliation, $transactions, $postings, $journals, $payments, $allocations, $settlements, $other, $reversals, $reversalJournals, $reversalLinks, $reversalSettlements, $journalFacts);
            $otherIntent = $reconciliation === null ? null : $other->get($reconciliation->bank_transaction_id)?->first();
            $allocationSummaries = $state === BankEntryDerivedState::FinancialStateInvalid || $reconciliation === null ? [] : $this->allocationSummaries($reconciliation, $payments, $allocations, $settlements, $reversalLinks, $reversalSettlements, $reversal);
            $summary = $state === BankEntryDerivedState::FinancialStateInvalid || $reconciliation === null || $posting === null ? null : new BankEntryFinancialSummary(new BankEntryReconciliationId(new Uuid($reconciliation->id)), new BankTransactionId(new Uuid($reconciliation->bank_transaction_id)), BankEntryReconciliationIntent::from($reconciliation->intent), new PostingDate(new DateTimeImmutable($reconciliation->posting_date)), new JournalEntryId(new Uuid($posting->journal_entry_id)), $allocationSummaries, $otherIntent === null ? null : new LedgerAccountId(new Uuid($otherIntent->contra_ledger_account_id)), $reversal === null ? null : new BankTransactionReversalId(new Uuid($reversal->id)));

            return new BankReconciliationSourceItem($this->entry($row), new AdministrationBankAccountId(new Uuid($row->administration_bank_account_id)), new BankStatementId(new Uuid($row->bank_statement_id)), new BankImportBatchId(new Uuid($row->bank_import_batch_id)), $row->statement_external_id, $state, $this->historyOrderer->order($history->get($row->id, collect())->map(fn (object $event): BankEntryReconciliationHistory => $this->history($event))->all()), $summary);
        })->filter(fn (BankReconciliationSourceItem $item): bool => in_array($item->state, $filter->states, true))->values()->all();
    }

    private function allocationSummaries(object $reconciliation, $payments, $allocations, $settlements, $reversalLinks, $reversalSettlements, ?object $reversal): array
    {
        $payment = $payments->get($reconciliation->bank_transaction_id, collect())->first();
        if ($payment === null) {
            return [];
        }
        $links = $reversal === null ? collect() : $reversalLinks->get($reversal->id, collect())->keyBy('payment_allocation_id');

        return $allocations->get($payment->id, collect())->map(function (object $allocation) use ($settlements, $links, $reversalSettlements): BankEntryFinancialAllocationSummary {
            $settlement = $settlements->get($allocation->id)->first();
            $link = $links->get($allocation->id);
            $reversalSettlement = $link === null ? null : $reversalSettlements->get($link->reversal_open_item_settlement_id);

            return new BankEntryFinancialAllocationSummary(new PaymentAllocationId(new Uuid($allocation->id)), new OpenItemId(new Uuid($allocation->open_item_id)), new Money($allocation->amount, new Currency($allocation->currency)), new OpenItemSettlementId(new Uuid($settlement->id)), $reversalSettlement === null ? null : new OpenItemSettlementId(new Uuid($reversalSettlement->id)));
        })->all();
    }

    private function financialReconciliation(object $row): BankEntryReconciliation
    {
        return new BankEntryReconciliation(new BankEntryReconciliationId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), new BankStatementEntryId(new Uuid($row->bank_statement_entry_id)), new BankTransactionId(new Uuid($row->bank_transaction_id)), BankEntryReconciliationIntent::from($row->intent), new DateTimeImmutable($row->booking_date), new PostingDate(new DateTimeImmutable($row->posting_date)), new UserId(new Uuid($row->actor_id)), new DateTimeImmutable($row->occurred_at), $row->replaces_reconciliation_id === null ? null : new BankEntryReconciliationId(new Uuid($row->replaces_reconciliation_id)));
    }

    private function financialState(object $source, ?array $ordered, ?object $active, ?object $reconciliation, $transactions, $postings, $journals, $payments, $allocations, $settlements, $other, $reversals, $reversalJournals, $reversalLinks, $reversalSettlements, $journalFacts): array
    {
        if ($ordered === null || ($active !== null && ($reconciliation === null || $active->bank_statement_entry_id !== $source->id))) {
            return [BankEntryDerivedState::FinancialStateInvalid, null, null];
        }
        if ($reconciliation === null) {
            return [$source->latest_action === BankEntryManualAction::Ignore->value ? BankEntryDerivedState::Ignored : BankEntryDerivedState::Unresolved, null, null];
        }
        $latest = $ordered[array_key_last($ordered)];
        if ($latest->id->toString() !== $reconciliation->id || ($active !== null && $latest->id->toString() !== $active->id)) {
            return [BankEntryDerivedState::FinancialStateInvalid, null, null];
        }
        $transaction = $transactions->get($reconciliation->bank_transaction_id);
        $postingRows = $postings->get($reconciliation->bank_transaction_id, collect());
        $posting = $postingRows->count() === 1 ? $postingRows->first() : null;
        $intentCount = BankEntryReconciliationIntent::from($reconciliation->intent) === BankEntryReconciliationIntent::Other ? $other->get($reconciliation->bank_transaction_id, collect())->count() : $payments->get($reconciliation->bank_transaction_id, collect())->count();
        $oppositeCount = BankEntryReconciliationIntent::from($reconciliation->intent) === BankEntryReconciliationIntent::Other ? $payments->get($reconciliation->bank_transaction_id, collect())->count() : $other->get($reconciliation->bank_transaction_id, collect())->count();
        $originalJournal = $posting === null ? null : $journalFacts->get($posting->journal_entry_id);
        $payment = $payments->get($reconciliation->bank_transaction_id, collect())->first();
        $paymentAllocations = $payment === null ? collect() : $allocations->get($payment->id, collect());
        $paymentGraphValid = $payment === null || ($paymentAllocations->isNotEmpty() && $paymentAllocations->every(function (object $allocation) use ($settlements): bool {
            $facts = $settlements->get($allocation->id, collect());

            return $facts->count() === 1 && $facts->first()->type === 'applied' && bccomp((string) $facts->first()->amount, (string) $allocation->amount, 4) === 0;
        }));
        $originalValid = $transaction !== null && $transaction->status === 'posted' && $posting !== null && $journals->get($posting->journal_entry_id)?->status === 'posted' && $originalJournal !== null && $originalJournal->line_count >= 2 && bccomp((string) $originalJournal->debit_total, (string) $originalJournal->credit_total, 4) === 0 && $intentCount === 1 && $oppositeCount === 0 && $paymentGraphValid;
        $reversalRows = $reversals->get($reconciliation->bank_transaction_id, collect());
        $reversal = $reversalRows->count() === 1 ? $reversalRows->first() : null;
        $reversalJournal = $reversal === null ? null : $journalFacts->get($reversal->reversal_journal_entry_id);
        $reversalValid = $reversal !== null && $reversal->original_journal_entry_id === $posting?->journal_entry_id && $reversalJournals->get($reversal->reversal_journal_entry_id)?->status === 'posted' && $reversalJournal !== null && $reversalJournal->line_count === $originalJournal?->line_count && bccomp((string) $reversalJournal->debit_total, (string) $reversalJournal->credit_total, 4) === 0 && bccomp((string) $reversalJournal->debit_total, (string) $originalJournal?->credit_total, 4) === 0;
        if ($reversalValid && $payment !== null) {
            $links = $reversalLinks->get($reversal->id, collect())->keyBy('payment_allocation_id');
            $reversalValid = $links->count() === $paymentAllocations->count() && $paymentAllocations->every(function (object $allocation) use ($links, $settlements, $reversalSettlements): bool {
                $original = $settlements->get($allocation->id, collect())->first();
                $link = $links->get($allocation->id);
                $reversed = $link === null ? null : $reversalSettlements->get($link->reversal_open_item_settlement_id);

                return $original !== null && $link !== null && $link->original_open_item_settlement_id === $original->id && $reversed !== null && $reversed->type === 'reversal' && $reversed->reversed_settlement_id === $original->id && bccomp((string) $reversed->amount, (string) $original->amount, 4) === 0;
            });
        }
        if (! $originalValid || $reversalRows->count() > 1 || ($active !== null && $reversal !== null) || ($active === null && ! $reversalValid)) {
            return [BankEntryDerivedState::FinancialStateInvalid, null, null];
        }

        return [$active !== null ? BankEntryDerivedState::Reconciled : BankEntryDerivedState::Reversed, $posting, $reversal];
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

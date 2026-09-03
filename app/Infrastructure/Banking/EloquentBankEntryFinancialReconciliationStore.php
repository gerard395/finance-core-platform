<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryFinancialHistoryOrderer;
use App\Application\Banking\BankEntryFinancialReconciliationStore;
use App\Application\Banking\BankEntryPromotionSource;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentBankEntryFinancialReconciliationStore implements BankEntryFinancialReconciliationStore
{
    public function __construct(private readonly BankEntryFinancialHistoryOrderer $orderer) {}

    public function lockSource(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryPromotionSource
    {
        $row = DB::table('bank_statement_entries as e')->join('bank_statements as s', fn ($join) => $join->on('s.id', '=', 'e.bank_statement_id')->on('s.administration_id', '=', 'e.administration_id'))->where('e.administration_id', $administrationId->toString())->where('e.id', $entryId->toString())->select(['e.*', 's.administration_bank_account_id'])->lockForUpdate()->first();

        return $row === null ? null : new BankEntryPromotionSource($this->entry($row), new AdministrationBankAccountId(new Uuid($row->administration_bank_account_id)));
    }

    public function active(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation
    {
        $row = DB::table('bank_entry_active_reconciliations as a')->join('bank_entry_reconciliations as r', fn ($join) => $join->on('r.id', '=', 'a.bank_entry_reconciliation_id')->on('r.administration_id', '=', 'a.administration_id'))->where('a.administration_id', $administrationId->toString())->where('a.bank_statement_entry_id', $entryId->toString())->select('r.*')->first();

        return $row === null ? null : $this->reconciliation($row);
    }

    public function latest(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation
    {
        $history = DB::table('bank_entry_reconciliations')->where('administration_id', $administrationId->toString())->where('bank_statement_entry_id', $entryId->toString())->get()->map(fn (object $row): BankEntryReconciliation => $this->reconciliation($row))->all();
        $ordered = $this->orderer->order($history);
        if ($ordered === null) {
            throw new RuntimeException('Financial reconciliation ancestry is corrupt or ambiguous.');
        }

        return $ordered === [] ? null : $ordered[array_key_last($ordered)];
    }

    public function byTransaction(AdministrationId $administrationId, BankTransactionId $transactionId): ?BankEntryReconciliation
    {
        $row = DB::table('bank_entry_reconciliations')->where('administration_id', $administrationId->toString())->where('bank_transaction_id', $transactionId->toString())->first();

        return $row === null ? null : $this->reconciliation($row);
    }

    public function append(BankEntryReconciliation $reconciliation): bool
    {
        try {
            return DB::table('bank_entry_reconciliations')->insert(['id' => $reconciliation->id->toString(), 'administration_id' => $reconciliation->administrationId->toString(), 'bank_statement_entry_id' => $reconciliation->entryId->toString(), 'bank_transaction_id' => $reconciliation->bankTransactionId->toString(), 'intent' => $reconciliation->intent->value, 'booking_date' => $reconciliation->bookingDate->format('Y-m-d'), 'posting_date' => $reconciliation->postingDate->value()->format('Y-m-d'), 'actor_id' => $reconciliation->actorId->toString(), 'occurred_at' => $reconciliation->occurredAt->format('Y-m-d H:i:s.u'), 'replaces_reconciliation_id' => $reconciliation->replacesId?->toString()]);
        } catch (QueryException) {
            return false;
        }
    }

    public function activate(BankEntryReconciliation $reconciliation): bool
    {
        try {
            return DB::table('bank_entry_active_reconciliations')->insert(['administration_id' => $reconciliation->administrationId->toString(), 'bank_statement_entry_id' => $reconciliation->entryId->toString(), 'bank_entry_reconciliation_id' => $reconciliation->id->toString()]);
        } catch (QueryException) {
            return false;
        }
    }

    public function deactivate(AdministrationId $administrationId, BankStatementEntryId $entryId, BankEntryReconciliationId $expected): bool
    {
        return DB::table('bank_entry_active_reconciliations')->where('administration_id', $administrationId->toString())->where('bank_statement_entry_id', $entryId->toString())->where('bank_entry_reconciliation_id', $expected->toString())->delete() === 1;
    }

    private function reconciliation(object $row): BankEntryReconciliation
    {
        return new BankEntryReconciliation(new BankEntryReconciliationId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), new BankStatementEntryId(new Uuid($row->bank_statement_entry_id)), new BankTransactionId(new Uuid($row->bank_transaction_id)), BankEntryReconciliationIntent::from($row->intent), new DateTimeImmutable($row->booking_date), new PostingDate(new DateTimeImmutable($row->posting_date)), new UserId(new Uuid($row->actor_id)), new DateTimeImmutable($row->occurred_at), $row->replaces_reconciliation_id === null ? null : new BankEntryReconciliationId(new Uuid($row->replaces_reconciliation_id)));
    }

    private function entry(object $row): BankStatementEntry
    {
        return new BankStatementEntry(new BankStatementEntryId(new Uuid($row->id)), new DateTimeImmutable($row->booking_date), $row->value_date === null ? null : new DateTimeImmutable($row->value_date), new Money($row->signed_amount, new Currency($row->currency)), BankEntryDirection::from($row->direction), (bool) $row->reversal, $row->account_servicer_reference, $row->entry_reference, $row->end_to_end_id, $row->counterparty_name, $row->counterparty_account, json_decode($row->remittance_lines, true, flags: JSON_THROW_ON_ERROR), $row->creditor_reference, $row->mandate_id, $row->bank_transaction_domain, $row->bank_transaction_family, $row->bank_transaction_subfamily, $row->bank_transaction_proprietary_code, json_decode($row->normalized_metadata, true, flags: JSON_THROW_ON_ERROR), (int) $row->source_ordinal);
    }
}

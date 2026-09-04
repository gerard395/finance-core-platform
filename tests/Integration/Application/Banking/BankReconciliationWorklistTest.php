<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankEntryFinancialReconciliationStore;
use App\Application\Banking\BankEntryManualHistoryRepository;
use App\Application\Banking\BankEntryPromotionSource;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Application\Banking\BankTransactionPosting;
use App\Application\Banking\BankTransactionPostingRepository;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Application\Banking\IgnoreBankStatementEntry;
use App\Application\Banking\ListBankReconciliationWorklist;
use App\Application\Banking\ManualReconciliationStatus;
use App\Application\Banking\PostBankTransaction;
use App\Application\Banking\PreparedPaymentAllocation;
use App\Application\Banking\ReconcileAndPostBankStatementEntry;
use App\Application\Banking\ReconcileBankStatementEntryResult;
use App\Application\Banking\ReconcileBankStatementEntryStatus;
use App\Application\Banking\RestoreIgnoredBankStatementEntry;
use App\Application\Banking\ReverseBankTransactionStatus;
use App\Application\Banking\ReverseReconciledBankTransaction;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class BankReconciliationWorklistTest extends TestCase
{
    use RefreshDatabase;

    private const string A = 'a8100000-0000-4000-8000-000000000001';

    private const string B = 'b8100000-0000-4000-8000-000000000001';

    private const string USER = 'a8200000-0000-4000-8000-000000000001';

    private const string ENTRY_A = 'a8500000-0000-4000-8000-000000000001';

    private const string ENTRY_B = 'b8500000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('testing', app()->environment());
        self::assertSame('testing', config('database.connections.mysql.database'));
        self::assertSame('testing', DB::selectOne('select database() as name')->name);
        $this->fixtures();
    }

    public function test_worklist_is_tenant_scoped_filterable_and_ignore_restore_is_append_only(): void
    {
        $before = $this->financialCounts();
        $reader = $this->app->make(ListBankReconciliationWorklist::class);
        $a = $reader->execute($this->admin(self::A), new BankReconciliationWorklistFilter);
        self::assertCount(1, $a);
        self::assertSame(self::ENTRY_A, $a[0]->source->entry->id->toString());
        self::assertSame(BankEntryDerivedState::Unresolved, $a[0]->source->state);
        self::assertSame('Other', $a[0]->source->entry->counterpartyName);
        self::assertCount(1, $reader->execute($this->admin(self::B), new BankReconciliationWorklistFilter));

        $ignore = $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), '  Geen match  ', $this->user());
        self::assertSame(ManualReconciliationStatus::Success, $ignore->status);
        self::assertSame(ManualReconciliationStatus::AlreadyIgnored, $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'opnieuw', $this->user())->status);
        self::assertSame(ManualReconciliationStatus::NotFound, $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::B), $this->entry(self::ENTRY_A), 'tenant probe', $this->user())->status);
        self::assertCount(0, $reader->execute($this->admin(self::A), new BankReconciliationWorklistFilter));
        $ignored = $reader->execute($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Ignored]));
        self::assertCount(1, $ignored);
        self::assertNull($ignored[0]->suggestion);
        self::assertSame('Geen match', $ignored[0]->source->manualHistory[0]->reason->value);

        $restore = $this->app->make(RestoreIgnoredBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'Opnieuw beoordelen', $this->user());
        self::assertSame(ManualReconciliationStatus::Success, $restore->status);
        self::assertSame(ManualReconciliationStatus::NotIgnored, $this->app->make(RestoreIgnoredBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'opnieuw', $this->user())->status);
        $history = $this->app->make(BankEntryManualHistoryRepository::class)->history($this->admin(self::A), $this->entry(self::ENTRY_A));
        self::assertSame([BankEntryManualAction::Ignore, BankEntryManualAction::RestoreFromIgnored], array_column($history, 'action'));
        self::assertSame($history[0]->id->toString(), $history[1]->predecessorId?->toString());
        self::assertLessThan($history[1]->sequence, $history[0]->sequence);
        self::assertSame($before, $this->financialCounts());
    }

    public function test_filters_and_reason_validation_do_not_leak_or_mutate(): void
    {
        $reader = $this->app->make(ListBankReconciliationWorklist::class);
        self::assertCount(1, $reader->execute($this->admin(self::A), new BankReconciliationWorklistFilter(search: 'A-REF')));
        self::assertCount(0, $reader->execute($this->admin(self::A), new BankReconciliationWorklistFilter(search: 'B-REF')));
        self::assertSame(ManualReconciliationStatus::IntegrityFailure, $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), ' ', $this->user())->status);
        self::assertSame(ManualReconciliationStatus::IntegrityFailure, $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), str_repeat('x', 501), $this->user())->status);
        self::assertDatabaseCount('bank_entry_reconciliation_history', 0);
    }

    public function test_database_rejects_non_allowlisted_actions_and_cross_tenant_predecessors(): void
    {
        $ignore = $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'ignore', $this->user());
        self::assertSame(ManualReconciliationStatus::Success, $ignore->status);
        try {
            DB::table('bank_entry_reconciliation_history')->insert($this->historyRow('a8600000-0000-4000-8000-000000000001', self::A, self::ENTRY_A, 'invalid', null));
            self::fail('The action allowlist must reject unknown values.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_entry_reconciliation_history', 1);
        }
        try {
            DB::table('bank_entry_reconciliation_history')->insert($this->historyRow('b8600000-0000-4000-8000-000000000001', self::B, self::ENTRY_B, 'restored_from_ignored', $ignore->historyId?->toString()));
            self::fail('A predecessor from another tenant/entry must be rejected.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_entry_reconciliation_history', 1);
        }
    }

    public function test_real_mysql_ignore_races_have_one_success_and_a_coherent_chain(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $outcomes = $this->race(['ignore', 'ignore']);
        sort($outcomes);
        $expected = [ManualReconciliationStatus::AlreadyIgnored->value, ManualReconciliationStatus::Success->value];
        sort($expected);
        self::assertSame($expected, $outcomes);
        DB::purge();
        self::assertDatabaseCount('bank_entry_reconciliation_history', 1);
    }

    public function test_real_mysql_ignore_restore_race_has_only_a_valid_serialization(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $outcomes = $this->race(['ignore', 'restore']);
        DB::purge();
        $history = $this->app->make(BankEntryManualHistoryRepository::class)->history($this->admin(self::A), $this->entry(self::ENTRY_A));
        self::assertContains(ManualReconciliationStatus::Success->value, $outcomes);
        self::assertContains(count($history), [1, 2]);
        self::assertSame(BankEntryManualAction::Ignore, $history[0]->action);
        if (count($history) === 2) {
            self::assertSame(BankEntryManualAction::RestoreFromIgnored, $history[1]->action);
            self::assertSame($history[0]->id->toString(), $history[1]->predecessorId?->toString());
        } else {
            self::assertContains(ManualReconciliationStatus::NotIgnored->value, $outcomes);
        }
    }

    public function test_other_promotion_reversal_derived_state_and_rereconciliation_are_atomic_and_tenant_scoped(): void
    {
        $this->financialSetup(self::A);
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $contra = new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003'));

        self::assertSame(ReconcileBankStatementEntryStatus::NotFound, $service->execute($this->admin(self::B), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra)->status);
        $first = $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $first->status);
        $this->assertDenial(ReconcileBankStatementEntryStatus::AlreadyReconciled, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra));
        self::assertDatabaseCount('bank_entry_reconciliations', 1);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        self::assertDatabaseCount('other_bank_transaction_intents', 1);
        self::assertDatabaseCount('payments', 0);
        self::assertDatabaseCount('open_item_settlements', 0);
        $reconciled = $this->app->make(ListBankReconciliationWorklist::class)->execute($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reconciled]));
        self::assertSame(BankEntryDerivedState::Reconciled, $reconciled[0]->source->state);
        self::assertSame($first->bankTransactionId?->toString(), $reconciled[0]->source->financial?->bankTransactionId->toString());

        $reversal = $this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), $first->bankTransactionId, $date, new BankTransactionReversalReason('Imported correction'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $reversal->status);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 0);
        $reversed = $this->app->make(ListBankReconciliationWorklist::class)->execute($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reversed]));
        self::assertSame(BankEntryDerivedState::Reversed, $reversed[0]->source->state);
        self::assertNotNull($reversed[0]->source->financial?->reversalId);

        $second = $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $second->status);
        self::assertNotSame($first->bankTransactionId?->toString(), $second->bankTransactionId?->toString());
        self::assertDatabaseCount('bank_entry_reconciliations', 2);
        $current = $this->app->make(BankReconciliationSourceReader::class)->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reconciled]));
        self::assertSame([], $current[0]->financial?->allocations);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        self::assertSame($first->reconciliationId?->toString(), DB::table('bank_entry_reconciliations')->where('id', $second->reconciliationId?->toString())->value('replaces_reconciliation_id'));
        DB::table('bank_entry_active_reconciliations')->where('bank_statement_entry_id', self::ENTRY_A)->update(['bank_entry_reconciliation_id' => $first->reconciliationId?->toString()]);
        self::assertCount(1, $this->app->make(BankReconciliationSourceReader::class)->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::FinancialStateInvalid])));
        try {
            DB::table('bank_entry_active_reconciliations')->where('bank_statement_entry_id', self::ENTRY_A)->update(['bank_statement_entry_id' => self::ENTRY_B]);
            self::fail('A cross-entry active pointer must be rejected.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        }
    }

    public function test_ignored_and_protected_other_denials_create_no_financial_truth(): void
    {
        $this->financialSetup(self::A);
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $bankLedger = new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000002'));
        $this->assertDenial(ReconcileBankStatementEntryStatus::InvalidContraAccount, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $bankLedger));
        self::assertSame(ManualReconciliationStatus::Success, $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'ignore', $this->user())->status);
        $this->assertDenial(ReconcileBankStatementEntryStatus::Ignored, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $bankLedger));
        self::assertDatabaseCount('bank_transactions', 0);
        self::assertDatabaseCount('bank_entry_reconciliations', 0);
    }

    public function test_structural_denial_matrix_has_zero_new_side_effects(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $relation = new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001'));
        $otherRelation = new RelationId(new Uuid('b8800000-0000-4000-8000-000000000001'));
        $account = new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003'));
        $eur = new Currency('EUR');
        $valid = new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000010')), $relation, $account, new Money('50', $eur), new Money('100', $eur), []);
        $this->assertDenial(ReconcileBankStatementEntryStatus::InvalidIntent, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::SupplierPayment, $date, $this->user(), [$valid]));
        $this->assertDenial(ReconcileBankStatementEntryStatus::AllocationIncomplete, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, $date, $this->user()));
        $this->assertDenial(ReconcileBankStatementEntryStatus::InvalidAllocation, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, $date, $this->user(), [$valid, $valid]));
        $mismatch = new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000011')), $otherRelation, $account, new Money('25', $eur), new Money('100', $eur), []);
        $partial = new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000010')), $relation, $account, new Money('25', $eur), new Money('100', $eur), []);
        $this->assertDenial(ReconcileBankStatementEntryStatus::RelationMismatch, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, $date, $this->user(), [$partial, $mismatch]));
    }

    public function test_period_and_configuration_denials_have_zero_new_side_effects(): void
    {
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $contra = new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003'));
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $this->assertDenial(ReconcileBankStatementEntryStatus::MissingPostingConfiguration, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra));

        $this->financialSetup(self::A);
        DB::table('accounting_periods')->delete();
        $this->assertDenial(ReconcileBankStatementEntryStatus::NoAccountingPeriod, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra));
        DB::table('accounting_periods')->insert(['id' => 'a8700000-0000-4000-8000-000000000005', 'administration_id' => self::A, 'book_year_id' => 'a8700000-0000-4000-8000-000000000004', 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertDenial(ReconcileBankStatementEntryStatus::PeriodClosed, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra));
        DB::table('accounting_periods')->where('id', 'a8700000-0000-4000-8000-000000000005')->update(['status' => 'open']);
        DB::table('accounting_periods')->insert(['id' => 'a8700000-0000-4000-8000-000000000006', 'administration_id' => self::A, 'book_year_id' => 'a8700000-0000-4000-8000-000000000004', 'code' => 'OVERLAP', 'label' => 'Overlap', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertDenial(ReconcileBankStatementEntryStatus::PeriodIntegrityFailure, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra));
    }

    public function test_open_balance_and_invalid_source_denials_have_zero_new_side_effects(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $entry = 'a8500000-0000-4000-8000-000000000040';
        $this->sourceEntry($entry, '-150', 'DBIT', 'EXCEEDS');
        $allocation = new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000012')), new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001')), new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000004')), new Money('150', new Currency('EUR')), new Money('100', new Currency('EUR')), []);
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $this->assertDenialForEntry($entry, ReconcileBankStatementEntryStatus::AllocationExceedsOpenBalance, fn () => $service->execute($this->admin(self::A), $this->entry($entry), BankEntryReconciliationIntent::SupplierPayment, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), [$allocation]));

        DB::table('bank_statement_entries')->where('id', self::ENTRY_A)->update(['direction' => 'DBIT']);
        $this->assertDenial(ReconcileBankStatementEntryStatus::FinancialStateInvalid, fn () => $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003'))));
    }

    public function test_customer_and_supplier_promotions_persist_multiple_partial_allocations_and_settlements(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $supplierEntry = 'a8500000-0000-4000-8000-000000000002';
        $this->sourceEntry($supplierEntry, '-50', 'DBIT', 'SUPPLIER-REF');
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $relation = new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001'));
        $eur = new Currency('EUR');
        $customerAllocations = [
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000010')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003')), new Money('20', $eur), new Money('100', $eur), []),
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000011')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003')), new Money('30', $eur), new Money('100', $eur), []),
        ];
        $supplierAllocations = [
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000012')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000004')), new Money('25', $eur), new Money('100', $eur), []),
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000013')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000004')), new Money('25', $eur), new Money('100', $eur), []),
        ];

        $customer = $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, $date, $this->user(), $customerAllocations);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $customer->status);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $service->execute($this->admin(self::A), $this->entry($supplierEntry), BankEntryReconciliationIntent::SupplierPayment, $date, $this->user(), $supplierAllocations)->status);
        self::assertDatabaseCount('payments', 2);
        self::assertDatabaseCount('payment_allocations', 4);
        self::assertDatabaseCount('open_item_settlements', 4);
        self::assertDatabaseCount('bank_entry_reconciliations', 2);
        $current = $this->app->make(BankReconciliationSourceReader::class)->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reconciled]));
        $customerSummary = collect($current)->first(fn ($item) => $item->financial?->bankTransactionId->toString() === $customer->bankTransactionId?->toString())?->financial;
        self::assertCount(2, $customerSummary?->allocations ?? []);
        self::assertNull($customerSummary?->allocations[0]->reversalSettlementId);
        self::assertSame(0, bccomp('80', (string) DB::table('open_items')->where('id', 'a8800000-0000-4000-8000-000000000010')->selectRaw('original_amount - (select sum(amount) from open_item_settlements where open_item_id = open_items.id) as remaining')->value('remaining'), 4));
        self::assertSame(0, bccomp('75', (string) DB::table('open_items')->where('id', 'a8800000-0000-4000-8000-000000000012')->selectRaw('original_amount - (select sum(amount) from open_item_settlements where open_item_id = open_items.id) as remaining')->value('remaining'), 4));
        $reversal = $this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), $customer->bankTransactionId, $date, new BankTransactionReversalReason('Reverse imported receipt'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $reversal->status);
        self::assertSame(2, $reversal->success?->reversedSettlementCount);
        self::assertSame(2, DB::table('open_item_settlements')->where('type', 'reversal')->count());
        self::assertSame(1, DB::table('bank_entry_active_reconciliations')->count());
        $reversed = $this->app->make(BankReconciliationSourceReader::class)->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reversed]));
        self::assertCount(2, $reversed[0]->financial?->allocations ?? []);
        self::assertNotNull($reversed[0]->financial?->allocations[0]->reversalSettlementId);
    }

    public function test_payment_reversal_refuses_an_allocation_without_its_original_settlement(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $promotion = $this->promoteCustomerWithTwoAllocations();
        $allocationId = DB::table('payments')->join('payment_allocations', 'payment_allocations.payment_id', '=', 'payments.id')->where('payments.bank_transaction_id', $promotion->bankTransactionId?->toString())->orderBy('payment_allocations.id')->value('payment_allocations.id');
        DB::table('open_item_settlements')->where('payment_allocation_id', $allocationId)->delete();
        $journals = DB::table('journal_entries')->count();

        $reversal = $this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), $promotion->bankTransactionId, new PostingDate(new DateTimeImmutable('2026-09-03')), new BankTransactionReversalReason('Corrupt payment graph'), $this->user());

        self::assertSame(ReverseBankTransactionStatus::FinancialStateInvalid, $reversal->status);
        self::assertDatabaseCount('bank_transaction_reversals', 0);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        self::assertSame($journals, DB::table('journal_entries')->count());
    }

    public function test_reversed_payment_readmodel_requires_every_exact_settlement_reversal(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $promotion = $this->promoteCustomerWithTwoAllocations();
        self::assertSame(ReverseBankTransactionStatus::Success, $this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), $promotion->bankTransactionId, new PostingDate(new DateTimeImmutable('2026-09-03')), new BankTransactionReversalReason('Complete reversal'), $this->user())->status);
        $reader = $this->app->make(BankReconciliationSourceReader::class);
        self::assertCount(1, $reader->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::Reversed])));
        foreach (['a8800000-0000-4000-8000-000000000010', 'a8800000-0000-4000-8000-000000000011'] as $openItemId) {
            self::assertSame(0, bccomp('0', (string) DB::table('open_item_settlements')->where('open_item_id', $openItemId)->selectRaw("SUM(CASE WHEN type = 'applied' THEN amount ELSE -amount END) AS net")->value('net'), 4));
        }
        $applied = (array) DB::table('open_item_settlements')->whereNotNull('payment_allocation_id')->orderBy('id')->first();
        try {
            DB::table('open_item_settlements')->insert([...$applied, 'id' => 'a8990000-0000-4000-8000-000000000003']);
            self::fail('An extra Settlement for one PaymentAllocation must be rejected.');
        } catch (QueryException) {
            self::assertSame(2, DB::table('open_item_settlements')->whereNotNull('payment_allocation_id')->count());
        }

        $link = (array) DB::table('bank_transaction_settlement_reversal_links')->orderBy('id')->first();
        try {
            DB::table('bank_transaction_settlement_reversal_links')->insert([...$link, 'id' => 'a8990000-0000-4000-8000-000000000001']);
            self::fail('A duplicate settlement reversal must be rejected.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_transaction_settlement_reversal_links', 2);
        }
        try {
            DB::table('bank_transaction_settlement_reversal_links')->insert([...$link, 'id' => 'a8990000-0000-4000-8000-000000000002', 'administration_id' => self::B]);
            self::fail('A wrong-tenant settlement reversal must be rejected.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_transaction_settlement_reversal_links', 2);
        }
        DB::table('bank_transaction_settlement_reversal_links')->where('id', $link['id'])->delete();
        self::assertCount(1, $reader->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::FinancialStateInvalid])));
        DB::table('bank_transaction_settlement_reversal_links')->insert($link);
        DB::table('open_item_settlements')->where('id', $link['reversal_open_item_settlement_id'])->update(['amount' => '999']);
        self::assertCount(1, $reader->list($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::FinancialStateInvalid])));
    }

    public function test_real_mysql_double_reconcile_and_ignore_race_serialize_on_source_entry(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $this->financialSetup(self::A);
        DB::commit();
        $double = $this->financialRace('reconcile', 'reconcile');
        sort($double);
        self::assertSame(['already_reconciled', 'success'], $double);
        DB::purge();
        self::assertDatabaseCount('bank_entry_reconciliations', 1);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        self::assertDatabaseCount('bank_transactions', 1);
        self::assertDatabaseCount('journal_entries', 1);
    }

    public function test_real_mysql_ignore_and_reconcile_cannot_both_be_active(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $this->financialSetup(self::A);
        DB::commit();
        $outcomes = $this->financialRace('ignore', 'reconcile');
        sort($outcomes);
        self::assertContains($outcomes, [['ignored', 'success'], ['already_reconciled', 'success']]);
        DB::purge();
        $active = DB::table('bank_entry_active_reconciliations')->count();
        $ignored = DB::table('bank_entry_reconciliation_history')->where('action', 'ignored')->count();
        self::assertSame(1, $active + $ignored);
    }

    public function test_real_mysql_reconcile_vs_reversal_and_rereconcile_races_keep_one_coherent_pointer(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $this->financialSetup(self::A);
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $date = new PostingDate(new DateTimeImmutable('2026-09-03'));
        $contra = new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003'));
        $first = $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, $date, $this->user(), contraAccountId: $contra);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $first->status);
        DB::commit();
        $race = $this->financialRace('reverse', 'reconcile', $first->bankTransactionId);
        DB::purge();
        self::assertContains('success', $race);
        self::assertLessThanOrEqual(1, DB::table('bank_entry_active_reconciliations')->count());
        self::assertSame(DB::table('bank_entry_active_reconciliations')->count(), DB::table('bank_entry_reconciliations as r')->leftJoin('bank_transaction_reversals as v', 'v.original_bank_transaction_id', '=', 'r.bank_transaction_id')->join('bank_entry_active_reconciliations as a', 'a.bank_entry_reconciliation_id', '=', 'r.id')->whereNull('v.id')->count());

        $activeTransaction = DB::table('bank_entry_active_reconciliations as a')->join('bank_entry_reconciliations as r', 'r.id', '=', 'a.bank_entry_reconciliation_id')->value('r.bank_transaction_id');
        if ($activeTransaction !== null) {
            self::assertSame(ReverseBankTransactionStatus::Success, $this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), new BankTransactionId(new Uuid($activeTransaction)), $date, new BankTransactionReversalReason('Prepare rereconcile race'), $this->user())->status);
        }
        DB::commit();
        $rereconcile = $this->financialRace('reconcile', 'reconcile');
        sort($rereconcile);
        self::assertSame(['already_reconciled', 'success'], $rereconcile);
        DB::purge();
        self::assertSame(1, DB::table('bank_entry_active_reconciliations')->count());
        self::assertContains(DB::table('bank_entry_reconciliations')->count(), [2, 3]);
    }

    public function test_real_mysql_two_imported_payments_cannot_over_settle_the_same_open_item(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $entries = ['a8500000-0000-4000-8000-000000000030', 'a8500000-0000-4000-8000-000000000031'];
        $this->sourceEntry($entries[0], '-70', 'DBIT', 'PAY-RACE-1');
        $this->sourceEntry($entries[1], '-70', 'DBIT', 'PAY-RACE-2');
        DB::commit();
        $outcomes = $this->importedPaymentRace($entries);
        sort($outcomes);
        self::assertSame(['allocation_exceeds_open_balance', 'success'], $outcomes);
        DB::purge();
        self::assertSame(1, DB::table('bank_entry_active_reconciliations')->count());
        self::assertSame(1, DB::table('bank_transactions')->count());
        self::assertSame(0, bccomp('70', (string) DB::table('open_item_settlements')->where('open_item_id', 'a8800000-0000-4000-8000-000000000012')->where('type', 'applied')->sum('amount'), 4));
    }

    public function test_readmodel_fails_closed_when_active_financial_graph_is_incomplete(): void
    {
        $this->financialSetup(self::A);
        $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003')));
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $result->status);

        DB::table('bank_transactions')->where('id', $result->bankTransactionId?->toString())->update(['status' => 'finalized']);
        $items = $this->app->make(ListBankReconciliationWorklist::class)->execute($this->admin(self::A), new BankReconciliationWorklistFilter(states: [BankEntryDerivedState::FinancialStateInvalid]));
        self::assertCount(1, $items);
        self::assertSame(BankEntryDerivedState::FinancialStateInvalid, $items[0]->source->state);
        self::assertNull($items[0]->source->financial);
    }

    public function test_outgoing_imported_other_posts_debit_contra_credit_bank_without_payment_facts(): void
    {
        $this->financialSetup(self::A);
        $entry = 'a8500000-0000-4000-8000-000000000020';
        $this->sourceEntry($entry, '-50', 'DBIT', 'OTHER-OUT');
        $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry($entry), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003')));

        self::assertSame(ReconcileBankStatementEntryStatus::Success, $result->status);
        self::assertDatabaseCount('other_bank_transaction_intents', 1);
        self::assertDatabaseCount('payments', 0);
        self::assertDatabaseCount('open_item_settlements', 0);
        self::assertDatabaseCount('bank_entry_reconciliations', 1);
        self::assertDatabaseCount('bank_entry_active_reconciliations', 1);
        $lines = DB::table('journal_entry_lines')->join('bank_transaction_postings', 'bank_transaction_postings.journal_entry_id', '=', 'journal_entry_lines.journal_entry_id')->where('bank_transaction_postings.bank_transaction_id', $result->bankTransactionId?->toString())->orderBy('journal_entry_lines.ledger_account_id')->get(['ledger_account_id', 'debit_amount', 'credit_amount']);
        self::assertSame([['ledger_account_id' => 'a8700000-0000-4000-8000-000000000002', 'debit_amount' => null, 'credit_amount' => '50'], ['ledger_account_id' => 'a8700000-0000-4000-8000-000000000003', 'debit_amount' => '50', 'credit_amount' => null]], $lines->map(fn ($line) => (array) $line)->all());
    }

    public function test_supplier_single_allocation_can_fully_or_partially_settle_an_open_item(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $service = $this->app->make(ReconcileAndPostBankStatementEntry::class);
        $relation = new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001'));
        $account = new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000004'));
        $eur = new Currency('EUR');
        foreach ([['a8500000-0000-4000-8000-000000000021', 'a8800000-0000-4000-8000-000000000012', '-100', 'SUPPLIER-FULL'], ['a8500000-0000-4000-8000-000000000022', 'a8800000-0000-4000-8000-000000000013', '-40', 'SUPPLIER-PART']] as [$entry, $item, $amount, $reference]) {
            $this->sourceEntry($entry, $amount, 'DBIT', $reference);
            $allocation = new PreparedPaymentAllocation(new OpenItemId(new Uuid($item)), $relation, $account, new Money(ltrim($amount, '-'), $eur), new Money('100', $eur), []);
            self::assertSame(ReconcileBankStatementEntryStatus::Success, $service->execute($this->admin(self::A), $this->entry($entry), BankEntryReconciliationIntent::SupplierPayment, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), [$allocation])->status);
        }
        self::assertDatabaseCount('payments', 2);
        self::assertDatabaseCount('payment_allocations', 2);
        self::assertDatabaseCount('open_item_settlements', 2);
        self::assertSame(0, bccomp('100', (string) DB::table('open_item_settlements')->where('open_item_id', 'a8800000-0000-4000-8000-000000000012')->value('amount'), 4));
        self::assertSame(0, bccomp('40', (string) DB::table('open_item_settlements')->where('open_item_id', 'a8800000-0000-4000-8000-000000000013')->value('amount'), 4));
    }

    public function test_failure_after_history_or_immediately_before_active_pointer_rolls_back_all_financial_truth(): void
    {
        $this->financialSetup(self::A);
        foreach (['append', 'activate'] as $boundary) {
            $inner = $this->app->make(BankEntryFinancialReconciliationStore::class);
            $this->app->instance(BankEntryFinancialReconciliationStore::class, new FaultingFinancialReconciliationStore($inner, $boundary));
            $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003')));
            self::assertSame(ReconcileBankStatementEntryStatus::ConcurrencyConflict, $result->status);
            foreach (['bank_transactions', 'other_bank_transaction_intents', 'journal_entries', 'bank_transaction_postings', 'bank_entry_reconciliations', 'bank_entry_active_reconciliations'] as $table) {
                self::assertSame(0, DB::table($table)->count(), $boundary.' left rows in '.$table);
            }
            $this->app->forgetInstance(ReconcileAndPostBankStatementEntry::class);
            $this->app->forgetInstance(BankEntryFinancialReconciliationStore::class);
        }
    }

    public function test_failure_boundaries_a_through_e_roll_back_the_complete_payment_graph(): void
    {
        $this->financialSetup(self::A);
        $this->paymentSetup();
        $relation = new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001'));
        $eur = new Currency('EUR');
        $allocations = [
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000010')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003')), new Money('25', $eur), new Money('100', $eur), []),
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000011')), $relation, new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003')), new Money('25', $eur), new Money('100', $eur), []),
        ];
        $baseline = $this->promotionCounts();

        foreach (['after_transaction', 'after_intent', 'after_journal', 'after_first_settlement', 'after_posting'] as $boundary) {
            $this->installPromotionFailure($boundary);
            $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), $allocations);
            self::assertContains($result->status, [ReconcileBankStatementEntryStatus::InvalidAllocation, ReconcileBankStatementEntryStatus::PostingFailure, ReconcileBankStatementEntryStatus::ConcurrencyConflict]);
            self::assertSame($baseline, $this->promotionCounts(), $boundary.' was not fully rolled back');
            $this->restorePromotionBindings();
        }
    }

    public function test_failure_after_other_intent_rolls_back_the_complete_graph(): void
    {
        $this->financialSetup(self::A);
        $baseline = $this->promotionCounts();
        $this->installPromotionFailure('after_intent');
        $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003')));
        self::assertSame(ReconcileBankStatementEntryStatus::PostingFailure, $result->status);
        self::assertSame($baseline, $this->promotionCounts());
        $this->restorePromotionBindings();
    }

    private function installPromotionFailure(string $boundary): void
    {
        if (in_array($boundary, ['after_transaction', 'after_intent'], true)) {
            $inner = $this->app->make(BankTransactionRepository::class);
            $this->app->instance(BankTransactionRepository::class, new FaultingBankTransactionRepository($inner, $boundary));
        } elseif ($boundary === 'after_journal') {
            $inner = $this->app->make(JournalEntryStore::class);
            $this->app->instance(JournalEntryStore::class, new FaultingJournalEntryStore($inner));
        } elseif ($boundary === 'after_first_settlement') {
            $inner = $this->app->make(OpenItemSettlementStore::class);
            $this->app->instance(OpenItemSettlementStore::class, new FaultingOpenItemSettlementStore($inner));
        } else {
            $inner = $this->app->make(BankTransactionPostingRepository::class);
            $this->app->instance(BankTransactionPostingRepository::class, new FaultingBankTransactionPostingRepository($inner));
        }
        foreach ([ReconcileAndPostBankStatementEntry::class, CreateManualBankTransaction::class, FinalizeBankTransaction::class, PostBankTransaction::class] as $service) {
            $this->app->forgetInstance($service);
        }
    }

    private function restorePromotionBindings(): void
    {
        foreach ([BankTransactionRepository::class, JournalEntryStore::class, OpenItemSettlementStore::class, BankTransactionPostingRepository::class, ReconcileAndPostBankStatementEntry::class, CreateManualBankTransaction::class, FinalizeBankTransaction::class, PostBankTransaction::class] as $service) {
            $this->app->forgetInstance($service);
        }
    }

    private function promotionCounts(): array
    {
        return array_map(fn (string $table): int => DB::table($table)->count(), array_combine(['bank_transactions', 'payments', 'payment_allocations', 'other_bank_transaction_intents', 'journal_entries', 'open_item_settlements', 'bank_transaction_postings', 'bank_entry_reconciliations', 'bank_entry_active_reconciliations'], ['bank_transactions', 'payments', 'payment_allocations', 'other_bank_transaction_intents', 'journal_entries', 'open_item_settlements', 'bank_transaction_postings', 'bank_entry_reconciliations', 'bank_entry_active_reconciliations']));
    }

    private function assertDenial(ReconcileBankStatementEntryStatus $expected, callable $operation): void
    {
        $this->assertDenialForEntry(self::ENTRY_A, $expected, $operation);
    }

    private function assertDenialForEntry(string $entryId, ReconcileBankStatementEntryStatus $expected, callable $operation): void
    {
        $counts = $this->promotionCounts();
        $balances = DB::table('open_items')->orderBy('id')->pluck('original_amount', 'id')->all();
        $source = (array) DB::table('bank_statement_entries')->where('id', $entryId)->first();
        self::assertSame($expected, $operation()->status);
        self::assertSame($counts, $this->promotionCounts(), $expected->value.' created financial facts');
        self::assertSame($balances, DB::table('open_items')->orderBy('id')->pluck('original_amount', 'id')->all(), $expected->value.' changed OpenItems');
        self::assertSame($source, (array) DB::table('bank_statement_entries')->where('id', $entryId)->first(), $expected->value.' changed source facts');
    }

    /** @param array{string, string} $operations @return list<string> */
    private function race(array $operations): array
    {
        $files = [tempnam(sys_get_temp_dir(), 'bir3-race-a-'), tempnam(sys_get_temp_dir(), 'bir3-race-b-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $service = $operations[$index] === 'ignore' ? $this->app->make(IgnoreBankStatementEntry::class) : $this->app->make(RestoreIgnoredBankStatementEntry::class);
                    $result = $service->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'race '.$operations[$index], $this->user());
                    file_put_contents($file, $result->status->value);
                    exit(0);
                } catch (Throwable $failure) {
                    file_put_contents($file, 'error:'.$failure::class.':'.$failure->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        $outcomes = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        self::assertSame([], array_values(array_filter($outcomes, static fn (string $outcome): bool => str_starts_with($outcome, 'error:'))), implode(', ', $outcomes));

        return $outcomes;
    }

    private function fixtures(): void
    {
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'BIR actor', 'email' => 'bir3@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([self::A => 'A', self::B => 'B'] as $id => $suffix) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'BIR3-'.$suffix, 'name' => 'BIR3 '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $bank = strtolower($suffix).'8300000-0000-4000-8000-000000000001';
            $batch = strtolower($suffix).'8400000-0000-4000-8000-000000000001';
            $statement = strtolower($suffix).'8450000-0000-4000-8000-000000000001';
            $entry = $suffix === 'A' ? self::ENTRY_A : self::ENTRY_B;
            DB::table('administration_bank_accounts')->insert(['id' => $bank, 'administration_id' => $id, 'iban' => 'NL91ABNA04171643'.($suffix === 'A' ? '00' : '01'), 'bic' => null, 'account_holder' => 'Holder '.$suffix, 'label' => 'Bank '.$suffix, 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('bank_import_batches')->insert(['id' => $batch, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'source_format' => 'camt.053', 'namespace_version' => 'camt.053.001.08', 'original_file_hash' => hash('sha256', $suffix), 'parser_version' => 'v1', 'canonicalization_version' => 'bir-canonical-entry-v1', 'actor_id' => self::USER, 'imported_at' => now(), 'artifact_reference' => 'retained/'.$suffix]);
            DB::table('bank_statements')->insert(['id' => $statement, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'source_format' => 'camt.053', 'namespace_version' => 'camt.053.001.08', 'bank_import_batch_id' => $batch, 'external_id' => $suffix.'-STATEMENT', 'electronic_sequence' => null, 'account_identity' => 'NL91ABNA04171643'.($suffix === 'A' ? '00' : '01'), 'currency' => 'EUR', 'opening_balance' => '0', 'closing_balance' => '50', 'period_from' => null, 'period_to' => null, 'canonical_statement_hash' => hash('sha256', 'statement'.$suffix), 'source_identity_kind' => 'external_id', 'source_identity_value' => $suffix.'-STATEMENT', 'source_identity_version' => 'bir-canonical-entry-v1', 'source_ordinal' => 1]);
            DB::table('bank_statement_entries')->insert(['id' => $entry, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'bank_statement_id' => $statement, 'booking_date' => '2026-09-03', 'value_date' => null, 'signed_amount' => '50', 'currency' => 'EUR', 'direction' => 'CRDT', 'reversal' => false, 'account_servicer_reference' => $suffix.'-REF', 'entry_reference' => null, 'end_to_end_id' => null, 'counterparty_name' => 'Other', 'counterparty_account' => null, 'remittance_lines' => json_encode([$suffix.'-REF']), 'creditor_reference' => null, 'mandate_id' => null, 'bank_transaction_domain' => null, 'bank_transaction_family' => null, 'bank_transaction_subfamily' => null, 'bank_transaction_proprietary_code' => null, 'normalized_metadata' => '{}', 'canonical_entry_hash' => hash('sha256', 'entry'.$suffix), 'deduplication_kind' => 'account_servicer_reference', 'deduplication_value' => $suffix.'-REF', 'deduplication_version' => 'bir-canonical-entry-v1', 'source_ordinal' => 1]);
        }
    }

    private function financialSetup(string $administrationId): void
    {
        $now = now();
        foreach ([['a8700000-0000-4000-8000-000000000002', '1100', 'Bank', 'asset'], ['a8700000-0000-4000-8000-000000000003', '4990', 'Other', 'expense']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => $administrationId, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => 'a8700000-0000-4000-8000-000000000001', 'administration_id' => $administrationId, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('banking_posting_configurations')->insert(['administration_id' => $administrationId, 'administration_bank_account_id' => 'a8300000-0000-4000-8000-000000000001', 'bank_journal_id' => 'a8700000-0000-4000-8000-000000000001', 'bank_ledger_account_id' => 'a8700000-0000-4000-8000-000000000002', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('book_years')->insert(['id' => 'a8700000-0000-4000-8000-000000000004', 'administration_id' => $administrationId, 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('accounting_periods')->insert(['id' => 'a8700000-0000-4000-8000-000000000005', 'administration_id' => $administrationId, 'book_year_id' => 'a8700000-0000-4000-8000-000000000004', 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function paymentSetup(): void
    {
        $now = now();
        DB::table('relations')->insert(['id' => 'a8800000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'code' => 'REL', 'display_name' => 'Relation', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['a8800000-0000-4000-8000-000000000003', '1300', 'AR', 'asset'], ['a8800000-0000-4000-8000-000000000004', '1400', 'AP', 'liability']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::A, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => 'a8800000-0000-4000-8000-000000000005', 'administration_id' => self::A, 'code' => 'OPEN', 'name' => 'Opening', 'type' => 'general', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['id' => 'a8800000-0000-4000-8000-000000000006', 'administration_id' => self::A, 'journal_id' => 'a8800000-0000-4000-8000-000000000005', 'posting_date' => '2026-09-01', 'reference' => 'OPEN', 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[10, 'receivable', 'debit', 3], [11, 'receivable', 'debit', 3], [12, 'payable', 'credit', 4], [13, 'payable', 'credit', 4]] as [$number, $type, $side, $account]) {
            DB::table('open_items')->insert(['id' => sprintf('a8800000-0000-4000-8000-%012d', $number), 'administration_id' => self::A, 'relation_id' => 'a8800000-0000-4000-8000-000000000001', 'journal_entry_id' => 'a8800000-0000-4000-8000-000000000006', 'control_ledger_account_id' => sprintf('a8800000-0000-4000-8000-%012d', $account), 'open_item_type' => $type, 'side' => $side, 'original_amount' => '100', 'currency' => 'EUR', 'opened_on' => '2026-09-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function promoteCustomerWithTwoAllocations(): ReconcileBankStatementEntryResult
    {
        $relation = new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001'));
        $account = new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000003'));
        $eur = new Currency('EUR');
        $allocations = [
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000010')), $relation, $account, new Money('20', $eur), new Money('100', $eur), []),
            new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000011')), $relation, $account, new Money('30', $eur), new Money('100', $eur), []),
        ];
        $result = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::CustomerReceipt, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), $allocations);
        self::assertSame(ReconcileBankStatementEntryStatus::Success, $result->status);

        return $result;
    }

    private function sourceEntry(string $id, string $amount, string $direction, string $reference): void
    {
        DB::table('bank_statement_entries')->insert(['id' => $id, 'administration_id' => self::A, 'administration_bank_account_id' => 'a8300000-0000-4000-8000-000000000001', 'bank_statement_id' => 'a8450000-0000-4000-8000-000000000001', 'booking_date' => '2026-09-03', 'value_date' => null, 'signed_amount' => $amount, 'currency' => 'EUR', 'direction' => $direction, 'reversal' => false, 'account_servicer_reference' => $reference, 'entry_reference' => null, 'end_to_end_id' => null, 'counterparty_name' => 'Relation', 'counterparty_account' => null, 'remittance_lines' => json_encode([$reference]), 'creditor_reference' => null, 'mandate_id' => null, 'bank_transaction_domain' => null, 'bank_transaction_family' => null, 'bank_transaction_subfamily' => null, 'bank_transaction_proprietary_code' => null, 'normalized_metadata' => '{}', 'canonical_entry_hash' => hash('sha256', $reference), 'deduplication_kind' => 'account_servicer_reference', 'deduplication_value' => $reference, 'deduplication_version' => 'bir-canonical-entry-v1', 'source_ordinal' => 2]);
    }

    /** @return list<string> */
    private function financialRace(string $first, string $second, ?BankTransactionId $transactionId = null): array
    {
        $files = [tempnam(sys_get_temp_dir(), 'bir4-race-a-'), tempnam(sys_get_temp_dir(), 'bir4-race-b-')];
        $children = [];
        foreach ([[$first, $files[0]], [$second, $files[1]]] as [$operation, $file]) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    if ($operation === 'ignore') {
                        $status = $this->app->make(IgnoreBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), 'race', $this->user())->status->value;
                    } elseif ($operation === 'reverse') {
                        $status = strtolower($this->app->make(ReverseReconciledBankTransaction::class)->execute($this->admin(self::A), $transactionId, new PostingDate(new DateTimeImmutable('2026-09-03')), new BankTransactionReversalReason('Concurrent reversal'), $this->user())->status->name);
                    } else {
                        $status = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry(self::ENTRY_A), BankEntryReconciliationIntent::Other, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), contraAccountId: new LedgerAccountId(new Uuid('a8700000-0000-4000-8000-000000000003')))->status->value;
                    }
                    file_put_contents($file, $status);
                    exit(0);
                } catch (Throwable $failure) {
                    file_put_contents($file, 'error:'.$failure::class.':'.$failure->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        $outcomes = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        self::assertSame([], array_values(array_filter($outcomes, static fn (string $outcome): bool => str_starts_with($outcome, 'error:'))), implode(', ', $outcomes));

        return $outcomes;
    }

    /** @param array{string, string} $entries @return list<string> */
    private function importedPaymentRace(array $entries): array
    {
        $files = [tempnam(sys_get_temp_dir(), 'bir4-payment-a-'), tempnam(sys_get_temp_dir(), 'bir4-payment-b-')];
        $children = [];
        foreach ($entries as $index => $entry) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $allocation = new PreparedPaymentAllocation(new OpenItemId(new Uuid('a8800000-0000-4000-8000-000000000012')), new RelationId(new Uuid('a8800000-0000-4000-8000-000000000001')), new LedgerAccountId(new Uuid('a8800000-0000-4000-8000-000000000004')), new Money('70', new Currency('EUR')), new Money('100', new Currency('EUR')), []);
                    $status = $this->app->make(ReconcileAndPostBankStatementEntry::class)->execute($this->admin(self::A), $this->entry($entry), BankEntryReconciliationIntent::SupplierPayment, new PostingDate(new DateTimeImmutable('2026-09-03')), $this->user(), [$allocation])->status->value;
                    file_put_contents($files[$index], $status);
                    exit(0);
                } catch (Throwable $failure) {
                    file_put_contents($files[$index], 'error:'.$failure::class);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $outcomes = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $outcomes;
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function entry(string $id): BankStatementEntryId
    {
        return new BankStatementEntryId(new Uuid($id));
    }

    private function user(): UserId
    {
        return new UserId(new Uuid(self::USER));
    }

    /** @return array<string, int> */
    private function financialCounts(): array
    {
        return ['bank_transactions' => DB::table('bank_transactions')->count(), 'payments' => DB::table('payments')->count(), 'payment_allocations' => DB::table('payment_allocations')->count(), 'journal_entries' => DB::table('journal_entries')->count(), 'open_items' => DB::table('open_items')->count(), 'settlements' => DB::table('open_item_settlements')->count(), 'matches' => DB::table('open_item_matches')->count(), 'reversals' => DB::table('bank_transaction_reversals')->count()];
    }

    /** @return array<string, mixed> */
    private function historyRow(string $id, string $administration, string $entry, string $action, ?string $predecessor): array
    {
        return ['id' => $id, 'administration_id' => $administration, 'bank_statement_entry_id' => $entry, 'action' => $action, 'predecessor_id' => $predecessor, 'reason' => 'probe', 'actor_id' => self::USER, 'occurred_at' => now()];
    }
}

final readonly class FaultingFinancialReconciliationStore implements BankEntryFinancialReconciliationStore
{
    public function __construct(private BankEntryFinancialReconciliationStore $inner, private string $boundary) {}

    public function lockSource(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryPromotionSource
    {
        return $this->inner->lockSource($administrationId, $entryId);
    }

    public function active(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation
    {
        return $this->inner->active($administrationId, $entryId);
    }

    public function latest(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation
    {
        return $this->inner->latest($administrationId, $entryId);
    }

    public function byTransaction(AdministrationId $administrationId, BankTransactionId $transactionId): ?BankEntryReconciliation
    {
        return $this->inner->byTransaction($administrationId, $transactionId);
    }

    public function append(BankEntryReconciliation $reconciliation): bool
    {
        return $this->boundary === 'append' ? false : $this->inner->append($reconciliation);
    }

    public function activate(BankEntryReconciliation $reconciliation): bool
    {
        return $this->boundary === 'activate' ? false : $this->inner->activate($reconciliation);
    }

    public function deactivate(AdministrationId $administrationId, BankStatementEntryId $entryId, BankEntryReconciliationId $expected): bool
    {
        return $this->inner->deactivate($administrationId, $entryId, $expected);
    }
}

final class FaultingBankTransactionRepository implements BankTransactionRepository
{
    public function __construct(private readonly BankTransactionRepository $inner, private readonly string $boundary) {}

    public function save(BankTransaction $transaction): void
    {
        if ($this->boundary === 'after_transaction') {
            DB::table('bank_transactions')->insert(['id' => $transaction->id()->toString(), 'administration_id' => $transaction->administrationId()->toString(), 'administration_bank_account_id' => $transaction->bankAccountId()->toString(), 'transaction_date' => $transaction->transactionDate()->value()->format('Y-m-d'), 'amount' => $transaction->amount()->amount(), 'currency' => $transaction->amount()->currency()->code(), 'reference' => $transaction->reference()->value(), 'description' => $transaction->description()->value(), 'status' => $transaction->status()->value, 'created_by' => $transaction->createdBy()->toString(), 'created_at' => $transaction->createdAt(), 'finalized_by' => null, 'finalized_at' => null, 'posted_by' => null, 'posted_at' => null]);
            throw new \RuntimeException('Injected after BankTransaction.');
        }
        $this->inner->save($transaction);
        if ($this->boundary === 'after_intent') {
            throw new \RuntimeException('Injected after financial intent.');
        }
    }

    public function find(AdministrationId $administrationId, BankTransactionId $id, bool $forUpdate = false): ?BankTransaction
    {
        return $this->inner->find($administrationId, $id, $forUpdate);
    }

    public function list(AdministrationId $administrationId): array
    {
        return $this->inner->list($administrationId);
    }
}

final readonly class FaultingJournalEntryStore implements JournalEntryStore
{
    public function __construct(private JournalEntryStore $inner) {}

    public function append(JournalEntry $journalEntry): void
    {
        $this->inner->append($journalEntry);
        throw new \RuntimeException('Injected after JournalEntry.');
    }
}

final class FaultingOpenItemSettlementStore implements OpenItemSettlementStore
{
    private int $count = 0;

    public function __construct(private readonly OpenItemSettlementStore $inner) {}

    public function appendSettlement(OpenItem $openItem, OpenItemSettlement $settlement, ?PaymentAllocationId $paymentAllocationId = null): void
    {
        $this->inner->appendSettlement($openItem, $settlement, $paymentAllocationId);
        if (++$this->count === 1) {
            throw new \RuntimeException('Injected after first Settlement.');
        }
    }
}

final readonly class FaultingBankTransactionPostingRepository implements BankTransactionPostingRepository
{
    public function __construct(private BankTransactionPostingRepository $inner) {}

    public function exists(AdministrationId $admin, BankTransactionId $id): bool
    {
        return $this->inner->exists($admin, $id);
    }

    public function find(AdministrationId $admin, BankTransactionId $id): ?BankTransactionPosting
    {
        return $this->inner->find($admin, $id);
    }

    public function settlementAmount(AdministrationId $admin, PaymentAllocationId $id): ?Money
    {
        return $this->inner->settlementAmount($admin, $id);
    }

    public function append(BankTransactionPostingId $id, AdministrationId $admin, BankTransactionId $tx, JournalEntryId $entry, PostingDate $date): void
    {
        $this->inner->append($id, $admin, $tx, $entry, $date);
        throw new \RuntimeException('Injected after BankTransactionPosting.');
    }
}

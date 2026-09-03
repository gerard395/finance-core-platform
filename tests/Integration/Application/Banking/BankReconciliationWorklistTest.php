<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankEntryManualHistoryRepository;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Application\Banking\IgnoreBankStatementEntry;
use App\Application\Banking\ListBankReconciliationWorklist;
use App\Application\Banking\ManualReconciliationStatus;
use App\Application\Banking\RestoreIgnoredBankStatementEntry;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
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
                    file_put_contents($file, 'error:'.$failure::class);
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

    private function fixtures(): void
    {
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'BIR actor', 'email' => 'bir3@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([self::A => 'A', self::B => 'B'] as $id => $suffix) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'BIR3-'.$suffix, 'name' => 'BIR3 '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $bank = strtolower($suffix).'8300000-0000-4000-8000-000000000001';
            $batch = strtolower($suffix).'8400000-0000-4000-8000-000000000001';
            $statement = strtolower($suffix).'8450000-0000-4000-8000-000000000001';
            $entry = $suffix === 'A' ? self::ENTRY_A : self::ENTRY_B;
            DB::table('administration_bank_accounts')->insert(['id' => $bank, 'administration_id' => $id, 'iban' => 'NL91ABNA04171643'.($suffix === 'A' ? '00' : '01'), 'bic' => null, 'account_holder' => $suffix, 'label' => $suffix, 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('bank_import_batches')->insert(['id' => $batch, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'source_format' => 'camt.053', 'namespace_version' => 'camt.053.001.08', 'original_file_hash' => hash('sha256', $suffix), 'parser_version' => 'v1', 'canonicalization_version' => 'bir-canonical-entry-v1', 'actor_id' => self::USER, 'imported_at' => now(), 'artifact_reference' => 'retained/'.$suffix]);
            DB::table('bank_statements')->insert(['id' => $statement, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'source_format' => 'camt.053', 'namespace_version' => 'camt.053.001.08', 'bank_import_batch_id' => $batch, 'external_id' => $suffix.'-STATEMENT', 'electronic_sequence' => null, 'account_identity' => 'NL91ABNA04171643'.($suffix === 'A' ? '00' : '01'), 'currency' => 'EUR', 'opening_balance' => '0', 'closing_balance' => '50', 'period_from' => null, 'period_to' => null, 'canonical_statement_hash' => hash('sha256', 'statement'.$suffix), 'source_identity_kind' => 'external_id', 'source_identity_value' => $suffix.'-STATEMENT', 'source_identity_version' => 'bir-canonical-entry-v1', 'source_ordinal' => 1]);
            DB::table('bank_statement_entries')->insert(['id' => $entry, 'administration_id' => $id, 'administration_bank_account_id' => $bank, 'bank_statement_id' => $statement, 'booking_date' => '2026-09-03', 'value_date' => null, 'signed_amount' => '50', 'currency' => 'EUR', 'direction' => 'CRDT', 'reversal' => false, 'account_servicer_reference' => $suffix.'-REF', 'entry_reference' => null, 'end_to_end_id' => null, 'counterparty_name' => 'Other', 'counterparty_account' => null, 'remittance_lines' => json_encode([$suffix.'-REF']), 'creditor_reference' => null, 'mandate_id' => null, 'bank_transaction_domain' => null, 'bank_transaction_family' => null, 'bank_transaction_subfamily' => null, 'bank_transaction_proprietary_code' => null, 'normalized_metadata' => '{}', 'canonical_entry_hash' => hash('sha256', 'entry'.$suffix), 'deduplication_kind' => 'account_servicer_reference', 'deduplication_value' => $suffix.'-REF', 'deduplication_version' => 'bir-canonical-entry-v1', 'source_ordinal' => 1]);
        }
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

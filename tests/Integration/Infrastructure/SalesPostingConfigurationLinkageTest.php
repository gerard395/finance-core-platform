<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Sales\SalesInvoicePosting;
use App\Application\Sales\SalesInvoicePostingAppendResult;
use App\Application\Sales\SalesInvoicePostingRepository;
use App\Application\Sales\SalesPostingConfiguration;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationReadStatus;
use App\Application\Sales\SalesPostingConfigurationStore;
use App\Application\Sales\SalesPostingConfigurationWriteResult;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoicePostingRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesPostingConfigurationRecord;
use App\Infrastructure\Sales\EloquentSalesInvoicePostingRepository;
use App\Infrastructure\Sales\EloquentSalesPostingConfiguration;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class SalesPostingConfigurationLinkageTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a0000000-0000-4000-8000-000000000001';

    private const B = 'b0000000-0000-4000-8000-000000000001';

    private EloquentSalesPostingConfiguration $configurations;

    private EloquentSalesInvoicePostingRepository $postings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurations = new EloquentSalesPostingConfiguration;
        $this->postings = new EloquentSalesInvoicePostingRepository;
        $this->seedTenant(self::A, 1);
        $this->seedTenant(self::B, 2);
    }

    public function test_configuration_is_missing_then_saves_and_reads_all_typed_mappings_exactly(): void
    {
        self::assertSame(SalesPostingConfigurationReadStatus::Missing, $this->configurations->read($this->administrationId(self::A))->status());

        $configuration = $this->configuration(self::A, 1);
        self::assertSame(SalesPostingConfigurationWriteResult::Saved, $this->configurations->save($configuration));
        $result = $this->configurations->read($this->administrationId(self::A));

        self::assertSame(SalesPostingConfigurationReadStatus::Success, $result->status());
        self::assertNotNull($result->configuration());
        self::assertSame($configuration->administrationId()->toString(), $result->configuration()->administrationId()->toString());
        self::assertSame($configuration->salesJournalId()->toString(), $result->configuration()->salesJournalId()->toString());
        self::assertSame($configuration->accountsReceivableLedgerAccountId()->toString(), $result->configuration()->accountsReceivableLedgerAccountId()->toString());
        self::assertSame($configuration->revenueLedgerAccountId()->toString(), $result->configuration()->revenueLedgerAccountId()->toString());
        self::assertSame($configuration->outputVatLedgerAccountId()->toString(), $result->configuration()->outputVatLedgerAccountId()->toString());
    }

    public function test_configuration_is_one_per_tenant_and_updates_without_tenant_leakage(): void
    {
        self::assertSame(SalesPostingConfigurationWriteResult::Saved, $this->configurations->save($this->configuration(self::A, 1)));
        self::assertSame(SalesPostingConfigurationWriteResult::Saved, $this->configurations->save($this->configuration(self::B, 2)));
        self::assertSame(SalesPostingConfigurationWriteResult::Saved, $this->configurations->save($this->configuration(self::A, 1, vatSequence: 2)));

        self::assertSame(2, SalesPostingConfigurationRecord::query()->count());
        self::assertSame($this->accountId(1, 2), $this->configurations->read($this->administrationId(self::A))->configuration()?->outputVatLedgerAccountId()->toString());
        self::assertSame($this->accountId(2, 3), $this->configurations->read($this->administrationId(self::B))->configuration()?->outputVatLedgerAccountId()->toString());
    }

    public function test_configuration_store_rejects_cross_tenant_or_inactive_references_without_sql_leakage(): void
    {
        foreach ([
            [$this->journalId(2), $this->accountId(1, 1), $this->accountId(1, 2), $this->accountId(1, 3)],
            [$this->journalId(1), $this->accountId(2, 1), $this->accountId(1, 2), $this->accountId(1, 3)],
            [$this->journalId(1), $this->accountId(1, 1), $this->accountId(2, 2), $this->accountId(1, 3)],
            [$this->journalId(1), $this->accountId(1, 1), $this->accountId(1, 2), $this->accountId(2, 3)],
        ] as [$journal, $receivable, $revenue, $vat]) {
            $crossTenant = new SalesPostingConfiguration(
                $this->administrationId(self::A),
                new JournalId(new Uuid($journal)),
                new LedgerAccountId(new Uuid($receivable)),
                new LedgerAccountId(new Uuid($revenue)),
                new LedgerAccountId(new Uuid($vat)),
            );
            self::assertSame(SalesPostingConfigurationWriteResult::InvalidReference, $this->configurations->save($crossTenant));
        }

        LedgerAccountRecord::query()->whereKey($this->accountId(1, 3))->update(['status' => 'inactive']);
        self::assertSame(SalesPostingConfigurationWriteResult::InvalidReference, $this->configurations->save($this->configuration(self::A, 1)));
        self::assertSame(0, SalesPostingConfigurationRecord::query()->count());
    }

    public function test_reader_reports_invalid_reference_when_configured_masterdata_becomes_inactive(): void
    {
        self::assertSame(SalesPostingConfigurationWriteResult::Saved, $this->configurations->save($this->configuration(self::A, 1)));
        JournalRecord::query()->whereKey($this->journalId(1))->update(['status' => 'inactive']);

        $result = $this->configurations->read($this->administrationId(self::A));
        self::assertSame(SalesPostingConfigurationReadStatus::InvalidReference, $result->status());
        self::assertNotNull($result->configuration());
    }

    public function test_database_rejects_cross_tenant_configuration_and_restricts_masterdata_deletes(): void
    {
        foreach ([
            'sales_journal_id' => $this->journalId(2),
            'accounts_receivable_ledger_account_id' => $this->accountId(2, 1),
            'revenue_ledger_account_id' => $this->accountId(2, 2),
            'output_vat_ledger_account_id' => $this->accountId(2, 3),
        ] as $field => $crossTenantId) {
            $attributes = $this->configurationAttributes(self::A, 1);
            $attributes[$field] = $crossTenantId;
            try {
                SalesPostingConfigurationRecord::query()->create($attributes);
                self::fail('Database must reject a cross-tenant configuration reference.');
            } catch (QueryException) {
                self::assertSame(0, SalesPostingConfigurationRecord::query()->count());
            }
        }

        SalesPostingConfigurationRecord::query()->create($this->configurationAttributes(self::A, 1));
        $this->assertRestricted(fn () => JournalRecord::query()->whereKey($this->journalId(1))->delete());
        $this->assertRestricted(fn () => LedgerAccountRecord::query()->whereKey($this->accountId(1, 1))->delete());
        $this->assertRestricted(fn () => LedgerAccountRecord::query()->whereKey($this->accountId(1, 2))->delete());
        $this->assertRestricted(fn () => LedgerAccountRecord::query()->whereKey($this->accountId(1, 3))->delete());
    }

    public function test_linkage_appends_roundtrips_and_is_tenant_scoped(): void
    {
        $postingA = $this->posting(self::A, 1, 1);
        $postingB = $this->posting(self::B, 2, 1);

        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($postingA));
        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($postingB));
        self::assertNull($this->postings->findForInvoice($this->administrationId(self::B), $postingA->salesInvoiceId()));

        $read = $this->postings->findForInvoice($this->administrationId(self::A), $postingA->salesInvoiceId());
        self::assertNotNull($read);
        self::assertSame($postingA->salesInvoiceId()->toString(), $read->salesInvoiceId()->toString());
        self::assertSame($postingA->journalEntryId()->toString(), $read->journalEntryId()->toString());
        self::assertSame($postingA->openItemId()->toString(), $read->openItemId()->toString());
        self::assertSame($postingA->createdAt()->format('Y-m-d H:i:s'), $read->createdAt()->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_attempts_have_one_durable_winner_and_never_overwrite(): void
    {
        $first = $this->posting(self::A, 1, 1);
        $second = new SalesInvoicePosting(
            $this->administrationId(self::A),
            $first->salesInvoiceId(),
            new JournalEntryId(new Uuid($this->entryId(1, 2))),
            new OpenItemId(new Uuid($this->openItemId(1, 2))),
            new DateTimeImmutable('2026-08-24 10:01:00'),
        );

        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($first));
        self::assertSame(SalesInvoicePostingAppendResult::AlreadyExists, (new EloquentSalesInvoicePostingRepository)->append($second));
        self::assertSame(1, SalesInvoicePostingRecord::query()->count());
        self::assertSame($first->journalEntryId()->toString(), SalesInvoicePostingRecord::query()->value('journal_entry_id'));

        try {
            SalesInvoicePostingRecord::query()->create($this->postingAttributes(self::A, 1, 1));
            self::fail('Database idempotency boundary must reject the duplicate invoice mapping.');
        } catch (QueryException) {
            self::assertSame(1, SalesInvoicePostingRecord::query()->count());
        }
    }

    public function test_two_different_invoices_may_be_linked_once_each(): void
    {
        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($this->posting(self::A, 1, 1)));
        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($this->posting(self::A, 1, 2)));
        self::assertSame(2, SalesInvoicePostingRecord::query()->count());
    }

    public function test_linkage_adapter_and_database_reject_cross_tenant_references(): void
    {
        foreach ([
            [$this->invoiceId(2, 1), $this->entryId(1, 1), $this->openItemId(1, 1)],
            [$this->invoiceId(1, 1), $this->entryId(2, 1), $this->openItemId(1, 1)],
            [$this->invoiceId(1, 1), $this->entryId(1, 1), $this->openItemId(2, 1)],
        ] as [$invoice, $entry, $openItem]) {
            try {
                $this->postings->append(new SalesInvoicePosting(
                    $this->administrationId(self::A),
                    new SalesInvoiceId(new Uuid($invoice)),
                    new JournalEntryId(new Uuid($entry)),
                    new OpenItemId(new Uuid($openItem)),
                    new DateTimeImmutable('2026-08-24 10:00:00'),
                ));
                self::fail('Adapter must reject a cross-tenant posting reference.');
            } catch (DomainException $exception) {
                self::assertSame('Sales invoice posting references must belong to the same Administration.', $exception->getMessage());
            }
        }

        self::assertSame(0, SalesInvoicePostingRecord::query()->count());
    }

    public function test_database_rejects_cross_tenant_open_item_and_invoice_links(): void
    {
        $attributes = $this->postingAttributes(self::A, 1, 1);
        $attributes['open_item_id'] = $this->openItemId(2, 1);
        $this->assertDatabaseRejects($attributes);

        $attributes = $this->postingAttributes(self::A, 1, 1);
        $attributes['sales_invoice_id'] = $this->invoiceId(2, 1);
        $this->assertDatabaseRejects($attributes);
    }

    public function test_linkage_restricts_source_deletes_and_exposes_append_only_contract(): void
    {
        self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($this->posting(self::A, 1, 1)));
        $this->assertRestricted(fn () => DB::table('sales_invoices')->where('id', $this->invoiceId(1, 1))->delete());
        $this->assertRestricted(fn () => DB::table('journal_entries')->where('id', $this->entryId(1, 1))->delete());
        $this->assertRestricted(fn () => DB::table('open_items')->where('id', $this->openItemId(1, 1))->delete());
        self::assertFalse(method_exists(SalesInvoicePostingRepository::class, 'update'));
        self::assertFalse(method_exists(SalesInvoicePostingRepository::class, 'delete'));
    }

    public function test_linkage_participates_in_outer_transaction_rollback_and_contracts_are_bound(): void
    {
        DB::beginTransaction();
        try {
            self::assertSame(SalesInvoicePostingAppendResult::Appended, $this->postings->append($this->posting(self::A, 1, 1)));
        } finally {
            DB::rollBack();
        }

        self::assertSame(0, SalesInvoicePostingRecord::query()->count());
        self::assertInstanceOf(EloquentSalesPostingConfiguration::class, $this->app->make(SalesPostingConfigurationReader::class));
        self::assertInstanceOf(EloquentSalesPostingConfiguration::class, $this->app->make(SalesPostingConfigurationStore::class));
        self::assertInstanceOf(EloquentSalesInvoicePostingRepository::class, $this->app->make(SalesInvoicePostingRepository::class));
    }

    public function test_concurrent_linkage_appends_have_exactly_one_durable_winner(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the linkage concurrency test.');
        }

        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'sales-posting-'), tempnam(sys_get_temp_dir(), 'sales-posting-')];
        $children = [];

        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = (new EloquentSalesInvoicePostingRepository)->append($this->posting(self::A, 1, 1));
                    file_put_contents($file, $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);

        self::assertSame(['AlreadyExists', 'Appended'], $results);
        DB::beginTransaction();
        self::assertSame(1, SalesInvoicePostingRecord::query()->where('administration_id', self::A)->where('sales_invoice_id', $this->invoiceId(1, 1))->count());
        self::assertSame($this->entryId(1, 1), SalesInvoicePostingRecord::query()->where('administration_id', self::A)->value('journal_entry_id'));
    }

    private function seedTenant(string $administration, int $tenant): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => $administration, 'code' => 'T'.$tenant, 'name' => 'Tenant '.$tenant, 'description' => null, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->relationId($tenant), 'administration_id' => $administration, 'code' => 'REL'.$tenant, 'display_name' => 'Customer '.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->customerId($tenant), 'administration_id' => $administration, 'relation_id' => $this->relationId($tenant), 'customer_number' => 'C'.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        JournalRecord::query()->create(['id' => $this->journalId($tenant), 'administration_id' => $administration, 'code' => 'SALES', 'name' => 'Sales journal', 'type' => 'sales', 'status' => 'active']);

        foreach ([1 => 'asset', 2 => 'revenue', 3 => 'liability'] as $sequence => $type) {
            LedgerAccountRecord::query()->create(['id' => $this->accountId($tenant, $sequence), 'administration_id' => $administration, 'code' => 'A'.$sequence, 'name' => 'Account '.$sequence, 'type' => $type, 'status' => 'active']);
        }

        for ($sequence = 1; $sequence <= 2; $sequence++) {
            DB::table('sales_invoices')->insert(['id' => $this->invoiceId($tenant, $sequence), 'administration_id' => $administration, 'sales_invoice_number' => 'INV-'.$tenant.'-'.$sequence, 'customer_id' => $this->customerId($tenant), 'customer_relation_id_snapshot' => $this->relationId($tenant), 'customer_number_snapshot' => 'C'.$tenant, 'customer_name_snapshot' => 'Customer '.$tenant, 'invoice_address_id_snapshot' => $this->addressId($tenant), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Street 1', 'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1000AA', 'invoice_city_snapshot' => 'Amsterdam', 'invoice_country_code_snapshot' => 'NL', 'source_order_id' => null, 'currency' => 'EUR', 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-23', 'status' => 'finalized', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('journal_entries')->insert(['id' => $this->entryId($tenant, $sequence), 'administration_id' => $administration, 'journal_id' => $this->journalId($tenant), 'posting_date' => '2026-08-24', 'reference' => 'Invoice posting '.$sequence, 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('open_items')->insert(['id' => $this->openItemId($tenant, $sequence), 'administration_id' => $administration, 'relation_id' => $this->relationId($tenant), 'journal_entry_id' => $this->entryId($tenant, $sequence), 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '121', 'currency' => 'EUR', 'opened_on' => '2026-08-24', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function configuration(string $administration, int $tenant, int $vatSequence = 3): SalesPostingConfiguration
    {
        return new SalesPostingConfiguration($this->administrationId($administration), new JournalId(new Uuid($this->journalId($tenant))), new LedgerAccountId(new Uuid($this->accountId($tenant, 1))), new LedgerAccountId(new Uuid($this->accountId($tenant, 2))), new LedgerAccountId(new Uuid($this->accountId($tenant, $vatSequence))));
    }

    private function configurationAttributes(string $administration, int $tenant): array
    {
        return ['administration_id' => $administration, 'sales_journal_id' => $this->journalId($tenant), 'accounts_receivable_ledger_account_id' => $this->accountId($tenant, 1), 'revenue_ledger_account_id' => $this->accountId($tenant, 2), 'output_vat_ledger_account_id' => $this->accountId($tenant, 3)];
    }

    private function posting(string $administration, int $tenant, int $sequence): SalesInvoicePosting
    {
        return new SalesInvoicePosting($this->administrationId($administration), new SalesInvoiceId(new Uuid($this->invoiceId($tenant, $sequence))), new JournalEntryId(new Uuid($this->entryId($tenant, $sequence))), new OpenItemId(new Uuid($this->openItemId($tenant, $sequence))), new DateTimeImmutable('2026-08-24 10:00:00'));
    }

    private function postingAttributes(string $administration, int $tenant, int $sequence): array
    {
        return ['administration_id' => $administration, 'sales_invoice_id' => $this->invoiceId($tenant, $sequence), 'journal_entry_id' => $this->entryId($tenant, $sequence), 'open_item_id' => $this->openItemId($tenant, $sequence), 'created_at' => '2026-08-24 10:00:00'];
    }

    private function assertDatabaseRejects(array $attributes): void
    {
        try {
            SalesInvoicePostingRecord::query()->create($attributes);
            self::fail('Database must reject a cross-tenant posting link.');
        } catch (QueryException) {
            self::assertSame(0, SalesInvoicePostingRecord::query()->count());
        }
    }

    private function assertRestricted(callable $delete): void
    {
        try {
            $delete();
            self::fail('Referenced source deletion must be restricted.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function journalId(int $tenant): string
    {
        return sprintf('%d0000000-0000-4000-8000-000000000001', 3 + $tenant);
    }

    private function accountId(int $tenant, int $sequence): string
    {
        return sprintf('%d1000000-0000-4000-8000-%012d', 3 + $tenant, $sequence);
    }

    private function relationId(int $tenant): string
    {
        return sprintf('%d2000000-0000-4000-8000-000000000001', 3 + $tenant);
    }

    private function customerId(int $tenant): string
    {
        return sprintf('%d3000000-0000-4000-8000-000000000001', 3 + $tenant);
    }

    private function addressId(int $tenant): string
    {
        return sprintf('%d4000000-0000-4000-8000-000000000001', 3 + $tenant);
    }

    private function invoiceId(int $tenant, int $sequence): string
    {
        return sprintf('%d5000000-0000-4000-8000-%012d', 3 + $tenant, $sequence);
    }

    private function entryId(int $tenant, int $sequence): string
    {
        return sprintf('%d6000000-0000-4000-8000-%012d', 3 + $tenant, $sequence);
    }

    private function openItemId(int $tenant, int $sequence): string
    {
        return sprintf('%d7000000-0000-4000-8000-%012d', 3 + $tenant, $sequence);
    }
}

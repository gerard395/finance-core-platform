<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Sales\PostSalesInvoice;
use App\Application\Sales\PostSalesInvoiceStatus;
use App\Application\Sales\PostSalesInvoiceWithTax;
use App\Application\Sales\SalesInvoicePosting;
use App\Application\Sales\SalesInvoicePostingAppendResult;
use App\Application\Sales\SalesInvoicePostingClock;
use App\Application\Sales\SalesInvoicePostingIdentityGenerator;
use App\Application\Sales\SalesInvoicePostingRepository;
use App\Application\Sales\SalesInvoicePostingSource;
use App\Application\Sales\SalesInvoiceReadinessChecker;
use App\Application\Sales\SalesInvoiceUpdater;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoicePostingRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxPostingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class PostSalesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'c0000000-0000-4000-8000-000000000001';

    private const B = 'd0000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTenant(self::A, 1);
        $this->seedTenant(self::B, 2);
    }

    public function test_finalized_invoice_posts_all_financial_truth_from_configuration_and_snapshots(): void
    {
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '21'], ['50', '9']]);
        DB::table('tax_codes')->where('administration_id', self::A)->update(['rate' => '6', 'status' => 'inactive']);
        DB::table('relations')->where('administration_id', self::A)->update(['display_name' => 'Renamed after finalization']);
        DB::table('customers')->where('administration_id', self::A)->update(['active' => false]);

        $result = $this->postInvoice(self::A, 1);

        self::assertSame(PostSalesInvoiceStatus::Success, $result->status());
        self::assertNotNull($result->journalEntryId());
        self::assertNotNull($result->openItemId());
        self::assertCount(2, $result->taxPostingIds());
        self::assertSame('posted', SalesInvoiceRecord::query()->findOrFail($this->invoiceId(1, 1))->getAttribute('status'));

        $entry = JournalEntryRecord::query()->findOrFail($result->journalEntryId()->toString());
        self::assertSame(self::A, $entry->getAttribute('administration_id'));
        self::assertSame($this->journalId(1), $entry->getAttribute('journal_id'));
        self::assertSame('2026-08-20', $entry->getAttribute('posting_date')->format('Y-m-d'));
        self::assertSame('INV-1-1', $entry->getAttribute('reference'));

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $entry->getAttribute('id'))->get();
        self::assertSame(['175.5'], $lines->where('ledger_account_id', $this->accountId(1, 1))->pluck('debit_amount')->map(static fn ($amount): string => (string) $amount)->all());
        self::assertSame(['50', '100'], $lines->where('ledger_account_id', $this->accountId(1, 2))->pluck('credit_amount')->map(static fn ($amount): string => (string) $amount)->sort()->values()->all());
        self::assertSame(['4.5', '21'], $lines->where('ledger_account_id', $this->accountId(1, 3))->pluck('credit_amount')->map(static fn ($amount): string => (string) $amount)->sort()->values()->all());

        $taxPostings = TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->get();
        $taxPostingsByRate = $taxPostings->keyBy('tax_rate');
        self::assertSame(['9', '21'], $taxPostings->pluck('tax_rate')->sort()->values()->all());
        self::assertSame('4.5', $taxPostingsByRate->get('9')?->getAttribute('tax_amount'));
        self::assertSame('21', $taxPostingsByRate->get('21')?->getAttribute('tax_amount'));
        self::assertSame(['sales_invoice'], $taxPostings->pluck('source_document_type')->unique()->values()->all());

        $openItem = OpenItemRecord::query()->findOrFail($result->openItemId()->toString());
        self::assertSame('receivable', $openItem->getAttribute('open_item_type'));
        self::assertSame('175.5', $openItem->getAttribute('original_amount'));
        self::assertSame('EUR', $openItem->getAttribute('currency'));
        self::assertSame($this->relationId(1), $openItem->getAttribute('relation_id'));
        self::assertSame($entry->getAttribute('id'), $openItem->getAttribute('journal_entry_id'));

        $linkage = SalesInvoicePostingRecord::query()->where('sales_invoice_id', $this->invoiceId(1, 1))->firstOrFail();
        self::assertSame($entry->getAttribute('id'), $linkage->getAttribute('journal_entry_id'));
        self::assertSame($openItem->getAttribute('id'), $linkage->getAttribute('open_item_id'));
        self::assertSame(0, DB::table('orders')->count());
    }

    public function test_zero_tax_preserves_audit_truth_without_vat_journal_line(): void
    {
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '0']]);

        $result = $this->postInvoice(self::A, 1);

        self::assertSame(PostSalesInvoiceStatus::Success, $result->status());
        self::assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId()?->toString())->count());
        self::assertSame(0, DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId()?->toString())->where('ledger_account_id', $this->accountId(1, 3))->count());
        self::assertSame('0', TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->value('tax_rate'));
        self::assertNull(TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->value('tax_journal_entry_line_id'));
        self::assertSame('100', OpenItemRecord::query()->value('original_amount'));
    }

    public function test_all_fiscal_treatments_post_balanced_and_preserve_reporting_truth(): void
    {
        $cases = [
            ['21', 'domestic_standard', 'domestic_standard', 'none', '21'],
            ['9', 'domestic_reduced', 'domestic_reduced', 'none', '9'],
            ['0', 'zero_rated', 'domestic_zero_rated', 'none', '0'],
            ['0', 'reverse_charge_eu_service', 'eu_services', 'service', '0'],
            ['0', 'intra_community_goods', 'intra_community_supplies', 'goods_supply', '0'],
            ['0', 'outside_scope', 'outside_scope', 'none', '0'],
            ['0', 'exempt', 'exempt', 'none', '0'],
        ];

        foreach ($cases as $offset => [$rate, $treatment, $vat, $icp, $tax]) {
            $sequence = $offset + 1;
            $this->seedInvoice(self::A, 1, $sequence, 'finalized', [['100', $rate]]);
            $this->setFiscalTruth($sequence, $treatment, $vat, $icp);

            $result = $this->postInvoice(self::A, $sequence);

            self::assertSame(PostSalesInvoiceStatus::Success, $result->status(), $treatment);
            $entryId = $result->journalEntryId()?->toString();
            $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $entryId)->get();
            self::assertSame($tax === '0' ? 2 : 3, $lines->count(), $treatment);
            self::assertSame($lines->sum('debit_amount'), $lines->sum('credit_amount'), $treatment);
            $posting = TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, $sequence))->firstOrFail();
            self::assertSame('100', $posting->getAttribute('taxable_base'), $treatment);
            self::assertSame($tax, $posting->getAttribute('tax_amount'), $treatment);
            self::assertSame($treatment, $posting->getAttribute('treatment'));
            self::assertSame($vat, $posting->getAttribute('vat_return_classification'));
            self::assertSame($icp, $posting->getAttribute('icp_classification'));
            self::assertSame($this->invoiceId(1, $sequence), $posting->getAttribute('source_document_id'));
            self::assertSame($this->id(1, 60 + $sequence, 1), $posting->getAttribute('source_line_id'));
            self::assertSame($tax === '0' ? '100' : (string) (100 + (int) $tax), OpenItemRecord::query()->where('journal_entry_id', $entryId)->value('original_amount'));
            self::assertSame($tax === '0' ? null : $this->accountId(1, 3), $lines->firstWhere('ledger_account_id', $this->accountId(1, 3))?->ledger_account_id);
        }
    }

    public function test_mixed_domestic_and_eu_service_posting_has_only_real_vat_and_independent_tax_facts(): void
    {
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '21'], ['50', '0']]);
        $this->setFiscalTruth(1, 'reverse_charge_eu_service', 'eu_services', 'service', 1);

        $result = $this->postInvoice(self::A, 1);

        self::assertSame(PostSalesInvoiceStatus::Success, $result->status());
        $postings = TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->orderBy('tax_rate', 'desc')->get();
        self::assertSame(['21', '0'], $postings->pluck('tax_rate')->all());
        self::assertSame(['21', '0'], $postings->pluck('tax_amount')->all());
        self::assertSame(['domestic_standard', 'reverse_charge_eu_service'], $postings->pluck('treatment')->all());
        self::assertSame('171', OpenItemRecord::query()->where('administration_id', self::A)->value('original_amount'));
        self::assertSame(1, DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId()?->toString())->where('ledger_account_id', $this->accountId(1, 3))->count());
    }

    public function test_posting_rejects_inconsistent_international_history_but_accepts_domestic_legacy_history(): void
    {
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '0']]);
        $this->setFiscalTruth(1, 'reverse_charge_eu_service', 'eu_services', 'service', fiscalPartyContext: false);
        self::assertSame(PostSalesInvoiceStatus::FinancialStateInconsistent, $this->postInvoice(self::A, 1)->status());
        $this->assertNoFinancialTruth();

        $this->seedInvoice(self::A, 1, 2, 'finalized', [['100', '21']]);
        self::assertSame(PostSalesInvoiceStatus::Success, $this->postInvoice(self::A, 2)->status());
    }

    public function test_missing_invalid_and_cross_tenant_inputs_write_nothing(): void
    {
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '21']]);
        DB::table('sales_posting_configurations')->where('administration_id', self::A)->delete();
        self::assertSame(PostSalesInvoiceStatus::ConfigurationMissing, $this->postInvoice(self::A, 1)->status());
        $this->assertNoFinancialTruth();

        $this->seedConfiguration(self::A, 1);
        DB::table('ledger_accounts')->where('id', $this->accountId(1, 3))->update(['status' => 'inactive']);
        self::assertSame(PostSalesInvoiceStatus::ConfigurationInvalid, $this->postInvoice(self::A, 1)->status());
        $this->assertNoFinancialTruth();

        self::assertSame(PostSalesInvoiceStatus::NotFound, $this->postInvoice(self::B, 1)->status());
        $this->assertNoFinancialTruth();
    }

    public function test_invalid_states_and_inconsistent_financial_states_are_never_reposted(): void
    {
        foreach (['draft', 'cancelled'] as $sequence => $status) {
            $this->seedInvoice(self::A, 1, $sequence + 1, $status, [['100', '21']]);
            self::assertSame(PostSalesInvoiceStatus::InvalidState, $this->postInvoice(self::A, $sequence + 1)->status());
        }

        $this->seedInvoice(self::A, 1, 3, 'posted', [['100', '21']]);
        self::assertSame(PostSalesInvoiceStatus::FinancialStateInconsistent, $this->postInvoice(self::A, 3)->status());

        $this->seedInvoice(self::A, 1, 5, 'paid', [['100', '21']]);
        self::assertSame(PostSalesInvoiceStatus::FinancialStateInconsistent, $this->postInvoice(self::A, 5)->status());

        $this->seedInvoice(self::A, 1, 4, 'finalized', [['100', '21']]);
        self::assertSame(PostSalesInvoiceStatus::Success, $this->postInvoice(self::A, 4)->status());
        $counts = $this->financialCounts();
        self::assertSame(PostSalesInvoiceStatus::AlreadyPosted, $this->postInvoice(self::A, 4)->status());
        self::assertSame($counts, $this->financialCounts());

        SalesInvoiceRecord::query()->whereKey($this->invoiceId(1, 4))->update(['status' => 'finalized']);
        self::assertSame(PostSalesInvoiceStatus::FinancialStateInconsistent, $this->postInvoice(self::A, 4)->status());
        self::assertSame($counts, $this->financialCounts());
    }

    public function test_every_persistence_failure_rolls_back_and_retry_remains_safe(): void
    {
        $stages = ['journal', 'tax', 'open_item', 'linkage', 'invoice'];
        foreach ($stages as $sequence => $stage) {
            $invoiceSequence = $sequence + 1;
            $this->seedInvoice(self::A, 1, $invoiceSequence, 'finalized', [['100', '21'], ['50', '9']]);
            $before = $this->financialCounts();
            $result = $this->useCase($stage)->execute($this->administrationId(self::A), $this->salesInvoiceId(1, $invoiceSequence));

            self::assertSame(PostSalesInvoiceStatus::PostingFailure, $result->status(), $stage);
            self::assertSame('finalized', SalesInvoiceRecord::query()->findOrFail($this->invoiceId(1, $invoiceSequence))->getAttribute('status'));
            self::assertSame(0, JournalEntryRecord::query()->where('reference', 'INV-1-'.$invoiceSequence)->count());
            self::assertSame(0, TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, $invoiceSequence))->count());
            self::assertSame(0, SalesInvoicePostingRecord::query()->where('sales_invoice_id', $this->invoiceId(1, $invoiceSequence))->count());
            self::assertSame($before, $this->financialCounts());

            self::assertSame(PostSalesInvoiceStatus::Success, $this->postInvoice(self::A, $invoiceSequence)->status(), $stage.' retry');
        }
    }

    public function test_two_concurrent_international_zero_tax_attempts_create_exactly_one_complete_posting(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the posting concurrency test.');
        }
        $this->seedInvoice(self::A, 1, 1, 'finalized', [['100', '0']]);
        $this->setFiscalTruth(1, 'reverse_charge_eu_service', 'eu_services', 'service');
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'invoice-post-'), tempnam(sys_get_temp_dir(), 'invoice-post-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $status = $this->app->make(PostSalesInvoice::class)->execute($this->administrationId(self::A), $this->salesInvoiceId(1, 1))->status();
                    file_put_contents($file, $status->name);
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

        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(1, SalesInvoicePostingRecord::query()->where('sales_invoice_id', $this->invoiceId(1, 1))->count());
        self::assertSame(1, JournalEntryRecord::query()->where('reference', 'INV-1-1')->count());
        self::assertSame(1, OpenItemRecord::query()->where('administration_id', self::A)->count());
        self::assertSame(1, TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->count());
        self::assertSame('reverse_charge_eu_service', TaxPostingRecord::query()->where('source_document_id', $this->invoiceId(1, 1))->value('treatment'));
        self::assertSame('posted', SalesInvoiceRecord::query()->findOrFail($this->invoiceId(1, 1))->getAttribute('status'));
        $this->removeCommittedConcurrencyFixtures();
        DB::beginTransaction();
    }

    private function postInvoice(string $administration, int $sequence)
    {
        return $this->app->make(PostSalesInvoice::class)->execute($this->administrationId($administration), $this->salesInvoiceId($administration === self::A ? 1 : 2, $sequence));
    }

    private function useCase(string $failure): PostSalesInvoice
    {
        $journal = $this->app->make(JournalEntryStore::class);
        $tax = $this->app->make(TaxPostingStore::class);
        $openItem = $this->app->make(OpenItemStore::class);
        $linkage = $this->app->make(SalesInvoicePostingRepository::class);
        $updater = $this->app->make(SalesInvoiceUpdater::class);

        return new PostSalesInvoice(
            $this->app->make(TransactionManager::class),
            $this->app->make(SalesInvoicePostingSource::class),
            $failure === 'invoice' ? new FailingInvoiceUpdater : $updater,
            $this->app->make(SalesPostingConfigurationReader::class),
            $failure === 'linkage' ? new FailingPostingRepository($linkage) : $linkage,
            $this->app->make(PostSalesInvoiceWithTax::class),
            $failure === 'journal' ? new FailingJournalEntryStore : $journal,
            $failure === 'tax' ? new FailingTaxPostingStore($tax) : $tax,
            $failure === 'open_item' ? new FailingOpenItemStore : $openItem,
            $this->app->make(SalesInvoicePostingIdentityGenerator::class),
            $this->app->make(SalesInvoicePostingClock::class),
            $this->app->make(SalesInvoiceReadinessChecker::class),
        );
    }

    private function seedTenant(string $administration, int $tenant): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => $administration, 'code' => 'POST'.$tenant, 'name' => 'Posting tenant '.$tenant, 'description' => null, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->relationId($tenant), 'administration_id' => $administration, 'code' => 'REL'.$tenant, 'display_name' => 'Snapshot Customer '.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->customerId($tenant), 'administration_id' => $administration, 'relation_id' => $this->relationId($tenant), 'customer_number' => 'C'.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journals')->insert(['id' => $this->journalId($tenant), 'administration_id' => $administration, 'code' => 'SALES', 'name' => 'Sales journal', 'type' => 'sales', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1 => 'asset', 2 => 'revenue', 3 => 'liability'] as $sequence => $type) {
            DB::table('ledger_accounts')->insert(['id' => $this->accountId($tenant, $sequence), 'administration_id' => $administration, 'code' => 'P'.$sequence, 'name' => 'Posting account '.$sequence, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([1 => ['VAT21', '21'], 2 => ['VAT9', '9'], 3 => ['VAT0', '0']] as $sequence => [$code, $rate]) {
            DB::table('tax_codes')->insert(['id' => $this->taxCodeId($tenant, $sequence), 'administration_id' => $administration, 'code' => $code, 'name' => $code, 'rate' => $rate, 'direction' => 'output', 'status' => 'active', 'treatment' => $rate === '0' ? 'zero_rated' : 'domestic_standard', 'vat_return_classification' => $rate === '0' ? 'domestic_zero_rated' : 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->seedConfiguration($administration, $tenant);
    }

    private function seedConfiguration(string $administration, int $tenant): void
    {
        DB::table('sales_posting_configurations')->updateOrInsert(['administration_id' => $administration], ['sales_journal_id' => $this->journalId($tenant), 'accounts_receivable_ledger_account_id' => $this->accountId($tenant, 1), 'revenue_ledger_account_id' => $this->accountId($tenant, 2), 'output_vat_ledger_account_id' => $this->accountId($tenant, 3), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<array{string, string}> $lines */
    private function seedInvoice(string $administration, int $tenant, int $sequence, string $status, array $lines): void
    {
        $now = now();
        DB::table('sales_invoices')->insert(['id' => $this->invoiceId($tenant, $sequence), 'administration_id' => $administration, 'sales_invoice_number' => 'INV-'.$tenant.'-'.$sequence, 'customer_id' => $this->customerId($tenant), 'customer_relation_id_snapshot' => $this->relationId($tenant), 'customer_number_snapshot' => 'C'.$tenant, 'customer_name_snapshot' => 'Snapshot Customer '.$tenant, 'invoice_address_id_snapshot' => $this->id($tenant, 40, 1), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Snapshot Street 1', 'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1000AA', 'invoice_city_snapshot' => 'Amsterdam', 'invoice_country_code_snapshot' => 'NL', 'source_order_id' => null, 'currency' => 'EUR', 'invoice_date' => '2026-08-20', 'due_date' => '2026-09-19', 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($lines as $lineSequence => [$amount, $rate]) {
            $taxSequence = $rate === '21' ? 1 : ($rate === '9' ? 2 : 3);
            DB::table('sales_invoice_lines')->insert(['id' => $this->id($tenant, 60 + $sequence, $lineSequence + 1), 'administration_id' => $administration, 'sales_invoice_id' => $this->invoiceId($tenant, $sequence), 'description' => 'Finalized line '.($lineSequence + 1), 'quantity' => '1', 'unit_price_amount' => $amount, 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->taxCodeId($tenant, $taxSequence), 'tax_code_snapshot' => 'VAT'.$rate, 'tax_name_snapshot' => 'VAT '.$rate, 'tax_rate_snapshot' => $rate, 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => $rate === '0' ? 'zero_rated' : 'domestic_standard', 'vat_return_classification_snapshot' => $rate === '0' ? 'domestic_zero_rated' : 'domestic_standard', 'icp_classification_snapshot' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function setFiscalTruth(int $invoiceSequence, string $treatment, string $vat, string $icp, int $lineSequence = 0, bool $fiscalPartyContext = true): void
    {
        DB::table('sales_invoice_lines')
            ->where('sales_invoice_id', $this->invoiceId(1, $invoiceSequence))
            ->where('id', $this->id(1, 60 + $invoiceSequence, $lineSequence + 1))
            ->update(['tax_treatment_snapshot' => $treatment, 'vat_return_classification_snapshot' => $vat, 'icp_classification_snapshot' => $icp]);

        if ($fiscalPartyContext && in_array($treatment, ['reverse_charge_eu_service', 'intra_community_goods'], true)) {
            DB::table('sales_invoices')->where('id', $this->invoiceId(1, $invoiceSequence))->update([
                'customer_vat_id_snapshot' => 'DE123456789',
                'customer_fiscal_jurisdiction_snapshot' => 'DE',
                'supplier_vat_id_snapshot' => 'NL123456789B01',
                'supplier_fiscal_jurisdiction_snapshot' => 'NL',
                'supply_date' => '2026-08-20',
            ]);
        }
    }

    private function assertNoFinancialTruth(): void
    {
        self::assertSame([0, 0, 0, 0], $this->financialCounts());
    }

    private function financialCounts(): array
    {
        return [JournalEntryRecord::query()->count(), TaxPostingRecord::query()->count(), OpenItemRecord::query()->count(), SalesInvoicePostingRecord::query()->count()];
    }

    private function removeCommittedConcurrencyFixtures(): void
    {
        $administrations = [self::A, self::B];
        DB::table('sales_invoice_postings')->whereIn('administration_id', $administrations)->delete();
        DB::table('tax_postings')->whereIn('administration_id', $administrations)->delete();
        DB::table('open_items')->whereIn('administration_id', $administrations)->delete();
        DB::table('journal_entry_lines')->whereIn('administration_id', $administrations)->delete();
        DB::table('journal_entries')->whereIn('administration_id', $administrations)->delete();
        DB::table('sales_invoice_lines')->whereIn('administration_id', $administrations)->delete();
        DB::table('sales_invoices')->whereIn('administration_id', $administrations)->delete();
        DB::table('sales_posting_configurations')->whereIn('administration_id', $administrations)->delete();
        DB::table('tax_codes')->whereIn('administration_id', $administrations)->delete();
        DB::table('ledger_accounts')->whereIn('administration_id', $administrations)->delete();
        DB::table('journals')->whereIn('administration_id', $administrations)->delete();
        DB::table('customers')->whereIn('administration_id', $administrations)->delete();
        DB::table('relations')->whereIn('administration_id', $administrations)->delete();
        DB::table('administrations')->whereIn('id', $administrations)->delete();
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function salesInvoiceId(int $tenant, int $sequence): SalesInvoiceId
    {
        return new SalesInvoiceId(new Uuid($this->invoiceId($tenant, $sequence)));
    }

    private function id(int $tenant, int $family, int $sequence): string
    {
        return sprintf('%x%07d-0000-4000-8000-%012d', $tenant + 11, $family, $sequence);
    }

    private function relationId(int $tenant): string
    {
        return $this->id($tenant, 20, 1);
    }

    private function customerId(int $tenant): string
    {
        return $this->id($tenant, 30, 1);
    }

    private function journalId(int $tenant): string
    {
        return $this->id($tenant, 10, 1);
    }

    private function accountId(int $tenant, int $sequence): string
    {
        return $this->id($tenant, 50, $sequence);
    }

    private function taxCodeId(int $tenant, int $sequence): string
    {
        return $this->id($tenant, 70, $sequence);
    }

    private function invoiceId(int $tenant, int $sequence): string
    {
        return $this->id($tenant, 80, $sequence);
    }
}

final class FailingJournalEntryStore implements JournalEntryStore
{
    public function append(JournalEntry $journalEntry): void
    {
        throw new RuntimeException('Forced JournalEntry failure.');
    }
}

final class FailingTaxPostingStore implements TaxPostingStore
{
    private int $attempt = 0;

    public function __construct(private readonly TaxPostingStore $delegate) {}

    public function append(TaxPosting $taxPosting): void
    {
        if (++$this->attempt === 2) {
            throw new RuntimeException('Forced TaxPosting N failure.');
        }
        $this->delegate->append($taxPosting);
    }
}

final class FailingOpenItemStore implements OpenItemStore
{
    public function append(OpenItem $openItem): void
    {
        throw new RuntimeException('Forced OpenItem failure.');
    }
}

final readonly class FailingPostingRepository implements SalesInvoicePostingRepository
{
    public function __construct(private SalesInvoicePostingRepository $delegate) {}

    public function findForInvoice(AdministrationId $administrationId, SalesInvoiceId $salesInvoiceId): ?SalesInvoicePosting
    {
        return $this->delegate->findForInvoice($administrationId, $salesInvoiceId);
    }

    public function append(SalesInvoicePosting $posting): SalesInvoicePostingAppendResult
    {
        return SalesInvoicePostingAppendResult::AlreadyExists;
    }
}

final class FailingInvoiceUpdater implements SalesInvoiceUpdater
{
    public function update(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult
    {
        return SalesInvoiceWriteResult::InvalidState;
    }
}

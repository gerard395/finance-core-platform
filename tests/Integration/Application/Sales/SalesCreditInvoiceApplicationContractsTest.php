<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\CancelSalesCreditInvoice;
use App\Application\Sales\CreateSalesCreditInvoiceFromInvoice;
use App\Application\Sales\FinalizeSalesCreditInvoice;
use App\Application\Sales\PostSalesCreditInvoice;
use App\Application\Sales\PostSalesInvoice;
use App\Application\Sales\PostSalesInvoiceStatus;
use App\Application\Sales\SalesCreditInvoiceConsistency;
use App\Application\Sales\SalesCreditInvoiceCreator;
use App\Application\Sales\SalesCreditInvoiceDetailReadRepository;
use App\Application\Sales\SalesCreditInvoiceIdentityGenerator;
use App\Application\Sales\SalesCreditInvoiceListQuery;
use App\Application\Sales\SalesCreditInvoiceListReadRepository;
use App\Application\Sales\SalesCreditInvoiceReadRepository;
use App\Application\Sales\SalesCreditInvoiceWriteResult;
use App\Application\Sales\SalesCreditSourceReader;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SalesCreditInvoiceApplicationContractsTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'e1000000-0000-4000-8000-000000000001';

    private const B = 'e2000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(SalesCreditInvoiceIdentityGenerator::class, new FixedCreditIdentity);
        $this->seedTenant(self::A, 1);
        $this->seedTenant(self::B, 2);
    }

    public function test_create_derives_full_credit_truth_and_roundtrips_snapshots_and_reads(): void
    {
        $source = $this->postedSource(self::A, 1, 1);
        DB::table('relations')->where('administration_id', self::A)->update(['display_name' => 'Later renamed']);
        DB::table('customers')->where('administration_id', self::A)->update(['active' => false]);

        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->create(self::A, $source));
        $record = SalesCreditInvoiceRecord::query()->firstOrFail();
        self::assertSame(self::A, $record->getAttribute('administration_id'));
        self::assertSame($source->toString(), $record->getAttribute('source_sales_invoice_id'));
        self::assertSame('Snapshot Customer 1', $record->getAttribute('customer_name_snapshot'));
        self::assertSame('EUR', $record->getAttribute('currency'));
        self::assertSame('C000001', $record->getAttribute('sales_credit_invoice_number'));
        $credit = $this->app->make(SalesCreditInvoiceReadRepository::class)->findForAdministration($this->admin(self::A), $this->creditId());
        self::assertNotNull($credit);
        self::assertSame(SalesCreditInvoiceStatus::Draft, $credit->status());
        self::assertCount(2, $credit->lines());
        self::assertSame(['50', '100'], collect($credit->lines())->map(fn ($line) => $line->lineTotal()->amount())->sort()->values()->all());
        self::assertSame('150', $credit->total()->amount());
        self::assertSame('Snapshot Customer 1', $credit->customerSnapshot()?->displayName()->toString());
        self::assertNotNull($this->app->make(SalesCreditInvoiceDetailReadRepository::class)->find($this->admin(self::A), $this->creditId()));
        $page = $this->app->make(SalesCreditInvoiceListReadRepository::class)->search(new SalesCreditInvoiceListQuery($this->admin(self::A), search: 'Snapshot'));
        self::assertSame(1, $page->total);
        self::assertSame('INV-1-1', $page->items[0]->sourceInvoiceNumber->value());
    }

    public function test_source_readiness_tenant_states_linkage_tax_paid_and_double_credit_are_enforced(): void
    {
        $draft = $this->seedInvoice(self::A, 1, 1, 'draft');
        self::assertSame(SalesCreditInvoiceWriteResult::SourceNotPosted, $this->create(self::A, $draft));
        self::assertSame(SalesCreditInvoiceWriteResult::NotFound, $this->create(self::B, $draft));

        $posted = $this->postedSource(self::A, 1, 2);
        DB::table('sales_invoice_postings')->where('sales_invoice_id', $posted->toString())->delete();
        self::assertSame(SalesCreditInvoiceWriteResult::FinancialPostingMissing, $this->create(self::A, $posted));

        $missingTax = $this->postedSource(self::A, 1, 3);
        DB::table('tax_postings')->where('source_document_id', $missingTax->toString())->delete();
        self::assertSame(SalesCreditInvoiceWriteResult::ReversalSourceMissing, $this->create(self::A, $missingTax));

        $paid = $this->postedSource(self::A, 1, 4);
        SalesInvoiceRecord::query()->whereKey($paid->toString())->update(['status' => 'paid']);
        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->create(self::A, $paid));
        self::assertSame(SalesCreditInvoiceWriteResult::AlreadyCredited, $this->create(self::A, $paid));
    }

    public function test_finalize_cancel_and_factual_posted_hydration_preserve_locked_lines(): void
    {
        $source = $this->postedSource(self::A, 1, 1);
        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->create(self::A, $source));
        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->app->make(FinalizeSalesCreditInvoice::class)->execute($this->admin(self::A), $this->creditId()));
        self::assertSame('finalized', SalesCreditInvoiceRecord::query()->value('status'));
        SalesCreditInvoiceRecord::query()->update(['status' => 'posted']);
        $posted = $this->app->make(SalesCreditInvoiceReadRepository::class)->findForAdministration($this->admin(self::A), $this->creditId());
        self::assertSame(SalesCreditInvoiceStatus::Posted, $posted?->status());
        self::assertSame(SalesCreditInvoiceWriteResult::InvalidState, $this->app->make(CancelSalesCreditInvoice::class)->execute($this->admin(self::A), $this->creditId()));

        DB::table('sales_credit_invoice_lines')->delete();
        DB::table('sales_credit_invoices')->delete();
        $second = $this->postedSource(self::A, 1, 2);
        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->create(self::A, $second));
        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->app->make(CancelSalesCreditInvoice::class)->execute($this->admin(self::A), $this->creditId()));
        self::assertSame(SalesCreditInvoiceStatus::Cancelled, $this->app->make(SalesCreditInvoiceReadRepository::class)->findForAdministration($this->admin(self::A), $this->creditId())?->status());
    }

    public function test_database_constraints_and_failed_create_roll_back_number(): void
    {
        $source = $this->postedSource(self::A, 1, 1);
        $before = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_credit_invoice')->value('next_value');
        $useCase = new CreateSalesCreditInvoiceFromInvoice($this->app->make(SalesCreditSourceReader::class), $this->app->make(SalesNumberAllocator::class), new FixedCreditIdentity, new FailingCreditCreator, new SalesCreditInvoiceConsistency, $this->app->make(TransactionManager::class));
        self::assertSame(SalesCreditInvoiceWriteResult::DuplicateIdentity, $useCase->execute($this->admin(self::A), $source, new DateTimeImmutable('2026-08-24')));
        self::assertSame($before, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_credit_invoice')->value('next_value'));
        self::assertFalse(class_exists(PostSalesCreditInvoice::class));

        self::assertSame(SalesCreditInvoiceWriteResult::Success, $this->create(self::A, $source));
        $this->expectException(QueryException::class);
        DB::table('sales_credit_invoice_lines')->insert(['id' => $this->id(2, 61, 1), 'administration_id' => self::B, 'sales_credit_invoice_id' => $this->creditId()->toString(), 'description' => 'cross tenant', 'quantity' => '1', 'unit_price_amount' => '1', 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function create(string $admin, SalesInvoiceId $source): SalesCreditInvoiceWriteResult
    {
        return $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin($admin), $source, new DateTimeImmutable('2026-08-24'));
    }

    private function postedSource(string $admin, int $tenant, int $sequence): SalesInvoiceId
    {
        $id = $this->seedInvoice($admin, $tenant, $sequence, 'finalized');
        self::assertSame(PostSalesInvoiceStatus::Success, $this->app->make(PostSalesInvoice::class)->execute($this->admin($admin), $id)->status());

        return $id;
    }

    private function seedTenant(string $admin, int $tenant): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => $admin, 'code' => 'CREDIT'.$tenant, 'name' => 'Credit '.$tenant, 'description' => null, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->id($tenant, 20, 1), 'administration_id' => $admin, 'code' => 'REL'.$tenant, 'display_name' => 'Snapshot Customer '.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->id($tenant, 30, 1), 'administration_id' => $admin, 'relation_id' => $this->id($tenant, 20, 1), 'customer_number' => 'C'.$tenant, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journals')->insert(['id' => $this->id($tenant, 10, 1), 'administration_id' => $admin, 'code' => 'SALES', 'name' => 'Sales', 'type' => 'sales', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1 => 'asset', 2 => 'revenue', 3 => 'liability'] as $n => $type) {
            DB::table('ledger_accounts')->insert(['id' => $this->id($tenant, 50, $n), 'administration_id' => $admin, 'code' => 'A'.$n, 'name' => 'Account '.$n, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([1 => ['VAT21', '21'], 2 => ['VAT9', '9']] as $n => [$code, $rate]) {
            DB::table('tax_codes')->insert(['id' => $this->id($tenant, 70, $n), 'administration_id' => $admin, 'code' => $code, 'name' => $code, 'rate' => $rate, 'direction' => 'output', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('sales_posting_configurations')->insert(['administration_id' => $admin, 'sales_journal_id' => $this->id($tenant, 10, 1), 'accounts_receivable_ledger_account_id' => $this->id($tenant, 50, 1), 'revenue_ledger_account_id' => $this->id($tenant, 50, 2), 'output_vat_ledger_account_id' => $this->id($tenant, 50, 3), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sales_number_sequences')->insert(['administration_id' => $admin, 'sequence_type' => 'sales_credit_invoice', 'next_value' => 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function seedInvoice(string $admin, int $tenant, int $sequence, string $status): SalesInvoiceId
    {
        $now = now();
        $invoice = $this->id($tenant, 80, $sequence);
        DB::table('sales_invoices')->insert(['id' => $invoice, 'administration_id' => $admin, 'sales_invoice_number' => 'INV-'.$tenant.'-'.$sequence, 'customer_id' => $this->id($tenant, 30, 1), 'customer_relation_id_snapshot' => $this->id($tenant, 20, 1), 'customer_number_snapshot' => 'C'.$tenant, 'customer_name_snapshot' => 'Snapshot Customer '.$tenant, 'invoice_address_id_snapshot' => $this->id($tenant, 40, 1), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Street 1', 'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1000AA', 'invoice_city_snapshot' => 'Amsterdam', 'invoice_country_code_snapshot' => 'NL', 'source_order_id' => null, 'currency' => 'EUR', 'invoice_date' => '2026-08-20', 'due_date' => '2026-09-20', 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1 => ['100', 1], 2 => ['50', 2]] as $line => [$amount, $tax]) {
            DB::table('sales_invoice_lines')->insert(['id' => $this->id($tenant, 60 + $sequence, $line), 'administration_id' => $admin, 'sales_invoice_id' => $invoice, 'description' => 'Source line '.$line, 'quantity' => '1', 'unit_price_amount' => $amount, 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->id($tenant, 70, $tax), 'tax_code_snapshot' => $tax === 1 ? 'VAT21' : 'VAT9', 'tax_name_snapshot' => 'VAT', 'tax_rate_snapshot' => $tax === 1 ? '21' : '9', 'tax_direction_snapshot' => 'output', 'created_at' => $now, 'updated_at' => $now]);
        }

        return new SalesInvoiceId(new Uuid($invoice));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function creditId(): SalesCreditInvoiceId
    {
        return new SalesCreditInvoiceId(new Uuid('ef000000-0000-4000-8000-000000000001'));
    }

    private function id(int $tenant, int $family, int $sequence): string
    {
        return sprintf('%x%07d-0000-4000-8000-%012d', $tenant + 12, $family, $sequence);
    }
}

final class FixedCreditIdentity implements SalesCreditInvoiceIdentityGenerator
{
    public function creditInvoiceId(): SalesCreditInvoiceId
    {
        return new SalesCreditInvoiceId(new Uuid('ef000000-0000-4000-8000-000000000001'));
    }
}

final class FailingCreditCreator implements SalesCreditInvoiceCreator
{
    public function create(AdministrationId $administrationId, SalesCreditInvoice $invoice): SalesCreditInvoiceWriteResult
    {
        return SalesCreditInvoiceWriteResult::DuplicateIdentity;
    }
}

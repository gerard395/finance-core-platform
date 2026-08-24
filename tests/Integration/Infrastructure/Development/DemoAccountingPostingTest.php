<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Development;

use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Sales\PostSalesInvoice;
use App\Application\Sales\PostSalesInvoiceStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DemoAccountingPostingTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMINISTRATION = '10000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        DB::table('administrations')->insert(['id' => self::ADMINISTRATION, 'code' => 'DEMOPOST', 'name' => 'Demo posting', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->id(20), 'administration_id' => self::ADMINISTRATION, 'code' => 'REL1', 'display_name' => 'Demo customer', 'vat_identification_number' => 'DE123456789', 'fiscal_jurisdiction' => 'DE', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->id(30), 'administration_id' => self::ADMINISTRATION, 'relation_id' => $this->id(20), 'customer_number' => 'C000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert([
            ['id' => $this->id(71), 'administration_id' => self::ADMINISTRATION, 'code' => 'BTW21', 'name' => 'BTW 21%', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->id(72), 'administration_id' => self::ADMINISTRATION, 'code' => 'EUDIENST', 'name' => 'Btw verlegd - dienst EU', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_provisioned_configuration_posts_domestic_and_eu_service_without_account_heuristics(): void
    {
        $masterData = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administrationId());
        $this->invoice(1, '21', 'domestic_standard', 'domestic_standard', 'none');
        $this->invoice(2, '0', 'reverse_charge_eu_service', 'eu_services', 'service');

        $domestic = $this->postInvoice(1);
        $euService = $this->postInvoice(2);

        self::assertSame(PostSalesInvoiceStatus::Success, $domestic->status());
        self::assertSame(PostSalesInvoiceStatus::Success, $euService->status());
        $domesticLines = DB::table('journal_entry_lines')->where('journal_entry_id', $domestic->journalEntryId()?->toString())->get();
        self::assertSame(3, $domesticLines->count());
        self::assertSame('121', $domesticLines->firstWhere('ledger_account_id', $masterData->accountsReceivable->id()->toString())?->debit_amount);
        self::assertSame('100', $domesticLines->firstWhere('ledger_account_id', $masterData->revenue->id()->toString())?->credit_amount);
        self::assertSame('21', $domesticLines->firstWhere('ledger_account_id', $masterData->outputVat->id()->toString())?->credit_amount);

        $euLines = DB::table('journal_entry_lines')->where('journal_entry_id', $euService->journalEntryId()?->toString())->get();
        self::assertSame(2, $euLines->count());
        self::assertSame('100', $euLines->firstWhere('ledger_account_id', $masterData->accountsReceivable->id()->toString())?->debit_amount);
        self::assertSame('100', $euLines->firstWhere('ledger_account_id', $masterData->revenue->id()->toString())?->credit_amount);
        self::assertNull($euLines->firstWhere('ledger_account_id', $masterData->outputVat->id()->toString()));
        $taxPosting = DB::table('tax_postings')->where('source_document_id', $this->invoiceId(2))->first();
        self::assertNotNull($taxPosting);
        self::assertSame('100', $taxPosting->taxable_base);
        self::assertSame('0', $taxPosting->tax_amount);
        self::assertSame('reverse_charge_eu_service', $taxPosting->treatment);
        self::assertSame('eu_services', $taxPosting->vat_return_classification);
        self::assertSame('service', $taxPosting->icp_classification);
    }

    private function invoice(int $sequence, string $rate, string $treatment, string $vat, string $icp): void
    {
        $now = now();
        DB::table('sales_invoices')->insert([
            'id' => $this->invoiceId($sequence), 'administration_id' => self::ADMINISTRATION, 'sales_invoice_number' => 'F'.sprintf('%06d', $sequence), 'customer_id' => $this->id(30),
            'customer_relation_id_snapshot' => $this->id(20), 'customer_number_snapshot' => 'C000001', 'customer_name_snapshot' => 'Demo customer',
            'invoice_address_id_snapshot' => $this->id(40), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Demo street 1',
            'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1000AA', 'invoice_city_snapshot' => 'Amsterdam', 'invoice_country_code_snapshot' => 'NL',
            'customer_vat_id_snapshot' => 'DE123456789', 'customer_fiscal_jurisdiction_snapshot' => 'DE', 'supplier_vat_id_snapshot' => 'NL123456789B01',
            'supplier_fiscal_jurisdiction_snapshot' => 'NL', 'supply_date' => '2026-08-24', 'source_order_id' => null, 'currency' => 'EUR',
            'invoice_date' => '2026-08-24', 'due_date' => '2026-09-23', 'status' => 'finalized', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('sales_invoice_lines')->insert([
            'id' => $this->id(60 + $sequence), 'administration_id' => self::ADMINISTRATION, 'sales_invoice_id' => $this->invoiceId($sequence), 'description' => 'Posting line',
            'quantity' => '1', 'unit_price_amount' => '100', 'currency' => 'EUR', 'tax_code_id_snapshot' => $rate === '21' ? $this->id(71) : $this->id(72),
            'tax_code_snapshot' => $rate === '21' ? 'BTW21' : 'EUDIENST', 'tax_name_snapshot' => 'Tax', 'tax_rate_snapshot' => $rate, 'tax_direction_snapshot' => 'output',
            'tax_treatment_snapshot' => $treatment, 'vat_return_classification_snapshot' => $vat, 'icp_classification_snapshot' => $icp, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function postInvoice(int $sequence)
    {
        return $this->app->make(PostSalesInvoice::class)->execute($this->administrationId(), new SalesInvoiceId(new Uuid($this->invoiceId($sequence))));
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::ADMINISTRATION));
    }

    private function invoiceId(int $sequence): string
    {
        return sprintf('50000000-0000-4000-8000-%012d', $sequence);
    }

    private function id(int $sequence): string
    {
        return sprintf('40000000-0000-4000-8000-%012d', $sequence);
    }
}

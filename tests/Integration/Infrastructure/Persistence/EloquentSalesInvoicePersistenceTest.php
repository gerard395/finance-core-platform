<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceListQuery;
use App\Application\Sales\SalesInvoiceListReadRepository;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerFiscalSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesSupplierFiscalSnapshot;
use App\Domain\Sales\ValueObjects\SalesTaxSnapshot;
use App\Domain\Sales\ValueObjects\SupplyDate;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OrderRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentSalesInvoicePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const A = '61000000-0000-4000-8000-000000000001';

    private const B = '62000000-0000-4000-8000-000000000001';

    private EloquentSalesInvoiceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentSalesInvoiceRepository;
        $this->tenant(self::A, 'A');
        $this->tenant(self::B, 'B');
    }

    public function test_every_factual_status_and_nullable_source_roundtrips_with_exact_snapshots_and_totals(): void
    {
        foreach (SalesInvoiceStatus::cases() as $index => $status) {
            $invoice = $this->invoice($index + 1, $status, null);
            self::assertSame(SalesInvoiceWriteResult::Success, $this->repository->create($this->admin(self::A), $invoice));
            $read = $this->repository->findForAdministration($this->admin(self::A), $invoice->id());

            self::assertSame($status, $read?->status());
            self::assertNull($read?->sourceOrderId());
            self::assertSame('Customer A', $read?->customerSnapshot()?->displayName()->value());
            self::assertSame('Invoice street 1', $read?->invoiceAddressSnapshot()?->addressLine()->value());
            self::assertSame('NL', $read?->invoiceAddressSnapshot()?->countryCode()->value());
            self::assertSame('VAT21', $read?->lines()[0]->taxSnapshot()?->taxCode()->value());
            self::assertSame('21', $read?->lines()[0]->taxSnapshot()?->taxRate()->value());
            self::assertSame('2026-08-21', $read?->invoiceDate()->format('Y-m-d'));
            self::assertSame('2026-09-20', $read?->dueDate()->format('Y-m-d'));
            self::assertSame('EUR', $read?->currency()->code());
            self::assertSame('DE123456789', $read?->customerFiscalSnapshot()?->vatIdentificationNumber()?->toString());
            self::assertSame('DE', $read?->customerFiscalSnapshot()?->fiscalJurisdiction()?->value());
            self::assertSame('NL123456789B01', $read?->supplierFiscalSnapshot()?->vatIdentificationNumber()?->toString());
            self::assertSame('NL', $read?->supplierFiscalSnapshot()?->fiscalJurisdiction()?->value());
            self::assertSame('2026-08-20', $read?->supplyDate()?->value()->format('Y-m-d'));
        }

        $sourced = $this->invoice(10, SalesInvoiceStatus::Draft, $this->sourceOrderId());
        self::assertSame(SalesInvoiceWriteResult::Success, $this->repository->create($this->admin(self::A), $sourced));
        self::assertTrue($this->sourceOrderId()->equals($this->repository->findForAdministration($this->admin(self::A), $sourced->id())?->sourceOrderId()));

        $detail = $this->app->make(SalesInvoiceDetailReadRepository::class)->find($this->admin(self::A), $sourced->id());
        self::assertSame('20', $detail?->netTotal()->amount());
        self::assertSame('4.2', $detail?->taxTotal()->amount());
        self::assertSame('24.2', $detail?->grossTotal()->amount());
    }

    public function test_historical_tax_and_customer_snapshots_do_not_follow_master_data_changes(): void
    {
        $invoice = $this->invoice(1, SalesInvoiceStatus::Draft, null);
        $this->repository->create($this->admin(self::A), $invoice);
        TaxCodeRecord::query()->whereKey($this->taxId(self::A)->toString())->update(['name' => 'Changed tax', 'rate' => '9', 'status' => 'inactive']);
        RelationRecord::query()->whereKey($this->relationId(self::A)->toString())->update(['display_name' => 'Renamed customer']);
        RelationRecord::query()->whereKey($this->relationId(self::A)->toString())->update(['vat_identification_number' => 'DE999999999', 'fiscal_jurisdiction' => 'FR']);
        AdministrationRecord::query()->whereKey(self::A)->update(['organisation_vat_number' => 'NL999999999B01', 'fiscal_jurisdiction' => 'BE']);

        $read = $this->repository->findForAdministration($this->admin(self::A), $invoice->id());
        self::assertSame('VAT high', $read?->lines()[0]->taxSnapshot()?->taxCodeName()->value());
        self::assertSame('21', $read?->lines()[0]->taxSnapshot()?->taxRate()->value());
        self::assertSame('Customer A', $read?->customerSnapshot()?->displayName()->value());
        self::assertSame('DE123456789', $read?->customerFiscalSnapshot()?->vatIdentificationNumber()?->toString());
        self::assertSame('NL123456789B01', $read?->supplierFiscalSnapshot()?->vatIdentificationNumber()?->toString());
    }

    public function test_tenant_reads_duplicate_conflicts_list_and_detail_are_safe(): void
    {
        $invoice = $this->invoice(1, SalesInvoiceStatus::Draft, null);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->repository->create($this->admin(self::A), $invoice));
        self::assertNull($this->repository->findForAdministration($this->admin(self::B), $invoice->id()));
        self::assertSame(SalesInvoiceWriteResult::DuplicateIdentity, $this->repository->create($this->admin(self::A), $invoice));
        self::assertSame(SalesInvoiceWriteResult::DuplicateNumber, $this->repository->create($this->admin(self::A), $this->invoice(2, SalesInvoiceStatus::Draft, null, 'F000001')));

        $reads = $this->app->make(SalesInvoiceListReadRepository::class);
        self::assertInstanceOf(EloquentSalesInvoiceReadRepository::class, $reads);
        self::assertSame(1, $reads->search(new SalesInvoiceListQuery($this->admin(self::A), search: 'Customer A', status: SalesInvoiceStatus::Draft))->total());
        self::assertSame(0, $reads->search(new SalesInvoiceListQuery($this->admin(self::B)))->total());
        self::assertNull($this->app->make(SalesInvoiceDetailReadRepository::class)->find($this->admin(self::B), $invoice->id()));
    }

    public function test_same_tenant_invoice_and_tax_constraints_reject_cross_tenant_lines(): void
    {
        $invoice = $this->invoice(1, SalesInvoiceStatus::Draft, null);
        $this->repository->create($this->admin(self::A), $invoice);

        $this->expectException(QueryException::class);
        SalesInvoiceLineRecord::query()->create(['id' => '6f000000-0000-4000-8000-000000000099', 'administration_id' => self::B, 'sales_invoice_id' => $invoice->id()->toString(), 'description' => 'Cross tenant', 'quantity' => '1', 'unit_price_amount' => '1', 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->taxId(self::B)->toString(), 'tax_code_snapshot' => 'VAT21', 'tax_name_snapshot' => 'VAT high', 'tax_rate_snapshot' => '21', 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => 'domestic_standard', 'vat_return_classification_snapshot' => 'domestic_standard', 'icp_classification_snapshot' => 'none']);
    }

    private function invoice(int $id, SalesInvoiceStatus $status, ?OrderId $source, ?string $number = null): SalesInvoice
    {
        return SalesInvoice::reconstitute($this->invoiceId($id), new SalesInvoiceNumber($number ?? sprintf('F%06d', $id)), $this->admin(self::A), $this->customerId(self::A), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), $source, $status, [$this->line($id)], $this->customerSnapshot(), $this->addressSnapshot(), new SalesCustomerFiscalSnapshot($this->relationId(self::A), new VatIdentificationNumber('DE123456789'), new CountryCode('DE')), new SalesSupplierFiscalSnapshot($this->admin(self::A), new VatIdentificationNumber('NL123456789B01'), new CountryCode('NL')), new SupplyDate(new DateTimeImmutable('2026-08-20')));
    }

    private function line(int $id): SalesInvoiceLine
    {
        return new SalesInvoiceLine(new SalesInvoiceLineId(new Uuid(sprintf('6f000000-0000-4000-8000-%012d', $id))), new LineDescription('Consulting'), new Quantity('2'), new Money('10', new Currency('EUR')), new SalesTaxSnapshot($this->taxId(self::A), new TaxCodeCode('VAT21'), new TaxCodeName('VAT high'), new TaxRate('21'), TaxPostingDirection::Output));
    }

    private function customerSnapshot(): SalesCustomerSnapshot
    {
        return new SalesCustomerSnapshot($this->customerId(self::A), $this->relationId(self::A), new CustomerNumber('C-A'), new DisplayName('Customer A'));
    }

    private function addressSnapshot(): SalesAddressSnapshot
    {
        return new SalesAddressSnapshot(new AddressId(new Uuid('6a000000-0000-4000-8000-000000000001')), AddressType::Invoice, new AddressLine('Invoice street 1'), null, new PostalCode('1234 AB'), new City('Amsterdam'), new CountryCode('NL'));
    }

    private function tenant(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => 'SI-'.$suffix, 'name' => 'Invoice tenant '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
        RelationRecord::query()->create(['id' => $this->relationId($id)->toString(), 'administration_id' => $id, 'code' => 'REL-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true]);
        CustomerRecord::query()->create(['id' => $this->customerId($id)->toString(), 'administration_id' => $id, 'relation_id' => $this->relationId($id)->toString(), 'customer_number' => 'C-'.$suffix, 'active' => true]);
        TaxCodeRecord::query()->create(['id' => $this->taxId($id)->toString(), 'administration_id' => $id, 'code' => 'VAT21', 'name' => 'VAT high', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none']);
        if ($id === self::A) {
            OrderRecord::query()->create(['id' => $this->sourceOrderId()->toString(), 'administration_id' => $id, 'order_number' => 'O000001', 'customer_id' => $this->customerId($id)->toString(), 'customer_relation_id_snapshot' => $this->relationId($id)->toString(), 'customer_number_snapshot' => 'C-A', 'customer_name_snapshot' => 'Customer A', 'source_quotation_id' => null, 'currency' => 'EUR', 'order_date' => '2026-08-20', 'status' => 'confirmed']);
        }
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function invoiceId(int $id): SalesInvoiceId
    {
        return new SalesInvoiceId(new Uuid(sprintf('6e000000-0000-4000-8000-%012d', $id)));
    }

    private function sourceOrderId(): OrderId
    {
        return new OrderId(new Uuid('6d000000-0000-4000-8000-000000000001'));
    }

    private function customerId(string $tenant): CustomerId
    {
        return new CustomerId(new Uuid($tenant === self::A ? '63000000-0000-4000-8000-000000000001' : '64000000-0000-4000-8000-000000000001'));
    }

    private function relationId(string $tenant): RelationId
    {
        return new RelationId(new Uuid($tenant === self::A ? '65000000-0000-4000-8000-000000000001' : '66000000-0000-4000-8000-000000000001'));
    }

    private function taxId(string $tenant): TaxCodeId
    {
        return new TaxCodeId(new Uuid($tenant === self::A ? '67000000-0000-4000-8000-000000000001' : '68000000-0000-4000-8000-000000000001'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\AddSalesInvoiceLine;
use App\Application\Sales\CancelSalesInvoice;
use App\Application\Sales\CreateSalesInvoice;
use App\Application\Sales\FinalizeSalesInvoice;
use App\Application\Sales\RemoveSalesInvoiceLine;
use App\Application\Sales\SalesInvoiceLineInput;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\UpdateSalesInvoice;
use App\Application\Sales\UpdateSalesInvoiceLine;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SupplyDate;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

final class SalesInvoiceApplicationContractsTest extends TestCase
{
    use RefreshDatabase;

    private const A = '71000000-0000-4000-8000-000000000001';

    private const B = '72000000-0000-4000-8000-000000000001';

    private const CUSTOMER_A = '73000000-0000-4000-8000-000000000001';

    private const CUSTOMER_B = '74000000-0000-4000-8000-000000000001';

    private const ADDRESS_A = '75000000-0000-4000-8000-000000000001';

    private const TAX_ACTIVE = '76000000-0000-4000-8000-000000000001';

    private const TAX_INACTIVE = '76000000-0000-4000-8000-000000000002';

    private const TAX_INPUT = '76000000-0000-4000-8000-000000000003';

    private const TAX_EU_SERVICE = '76000000-0000-4000-8000-000000000004';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant(self::A, self::CUSTOMER_A, 'A');
        $this->tenant(self::B, self::CUSTOMER_B, 'B');
        RelationAddressRecord::query()->create(['address_id' => self::ADDRESS_A, 'administration_id' => self::A, 'relation_id' => $this->relation(self::CUSTOMER_A), 'address_type' => 'invoice', 'address_line_1' => 'Invoice street 1', 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'active' => true]);
        $this->tax(self::TAX_ACTIVE, 'VAT21', '21', 'output', 'active');
        $this->tax(self::TAX_INACTIVE, 'VAT09', '9', 'output', 'inactive');
        $this->tax(self::TAX_INPUT, 'IN21', '21', 'input', 'active');
        $this->tax(self::TAX_EU_SERVICE, 'EUDIENST', '0', 'output', 'active', 'reverse_charge_eu_service', 'eu_services', 'service');
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::A));
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::B));
    }

    public function test_generic_create_is_direct_only_and_captures_number_customer_address_and_tax_snapshots(): void
    {
        $parameters = (new ReflectionMethod(CreateSalesInvoice::class, 'execute'))->getParameters();
        self::assertCount(8, $parameters);
        self::assertNotContains('sourceOrderId', array_map(static fn ($parameter): string => $parameter->getName(), $parameters));

        $id = $this->invoiceId(1);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->create($id, [$this->line(1)]));
        $invoice = $this->read($id);
        self::assertSame('F000001', $invoice?->number()->value());
        self::assertNull($invoice?->sourceOrderId());
        self::assertSame('Customer A', $invoice?->customerSnapshot()?->displayName()->value());
        self::assertSame('Invoice street 1', $invoice?->invoiceAddressSnapshot()?->addressLine()->value());
        self::assertSame('VAT21', $invoice?->lines()[0]->taxSnapshot()?->taxCode()->value());
        self::assertSame('21', $invoice?->lines()[0]->taxSnapshot()?->taxRate()->value());
    }

    public function test_customer_address_and_tax_failures_are_typed_and_do_not_allocate_numbers(): void
    {
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => false]);
        self::assertSame(SalesInvoiceWriteResult::InactiveCustomer, $this->create($this->invoiceId(1)));
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => true]);
        self::assertSame(SalesInvoiceWriteResult::CustomerNotFound, $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $this->invoiceId(2), new CustomerId(new Uuid(self::CUSTOMER_B)), $this->addressId(), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20')));
        RelationAddressRecord::query()->whereKey(self::ADDRESS_A)->update(['active' => false]);
        self::assertSame(SalesInvoiceWriteResult::MissingInvoiceAddress, $this->create($this->invoiceId(3)));
        RelationAddressRecord::query()->whereKey(self::ADDRESS_A)->update(['active' => true]);
        self::assertSame(SalesInvoiceWriteResult::TaxCodeInactive, $this->create($this->invoiceId(4), [$this->line(4, self::TAX_INACTIVE)]));
        self::assertSame(SalesInvoiceWriteResult::WrongTaxDirection, $this->create($this->invoiceId(5), [$this->line(5, self::TAX_INPUT)]));
        self::assertSame(SalesInvoiceWriteResult::TaxCodeNotFound, $this->create($this->invoiceId(6), [$this->line(6, '76000000-0000-4000-8000-000000000099')]));
        self::assertSame(1, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
    }

    public function test_persistence_conflict_rolls_back_number_and_draft_header_line_mutations_are_exact(): void
    {
        $id = $this->invoiceId(1);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->create($id));
        $before = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value');
        self::assertSame(SalesInvoiceWriteResult::DuplicateIdentity, $this->create($id));
        self::assertSame($before, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));

        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(UpdateSalesInvoice::class)->execute($this->admin(self::A), $id, new DateTimeImmutable('2026-08-22'), new DateTimeImmutable('2026-09-22')));
        self::assertSame(SalesInvoiceWriteResult::InvalidState, $this->app->make(UpdateSalesInvoice::class)->execute($this->admin(self::A), $id, new DateTimeImmutable('2026-09-23'), new DateTimeImmutable('2026-09-22')));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(AddSalesInvoiceLine::class)->execute($this->admin(self::A), $id, $this->line(1)));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(UpdateSalesInvoiceLine::class)->execute($this->admin(self::A), $id, $this->line(1, self::TAX_ACTIVE, '1.25', '10.123456')));
        self::assertSame('12.65432', $this->read($id)?->lines()[0]->lineTotal()->amount());
        self::assertSame(SalesInvoiceWriteResult::TaxCalculationFailure, $this->app->make(UpdateSalesInvoiceLine::class)->execute($this->admin(self::A), $id, $this->line(1, self::TAX_ACTIVE, '0.00000001', '0.00000001')));
        self::assertSame('12.65432', $this->read($id)?->lines()[0]->lineTotal()->amount());
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(RemoveSalesInvoiceLine::class)->execute($this->admin(self::A), $id, $this->lineId(1)));
        self::assertSame([], $this->read($id)?->lines());
    }

    public function test_nonrepresentable_tax_finalize_cancel_and_non_draft_locking_follow_domain_contracts(): void
    {
        $bad = $this->invoiceId(1);
        self::assertSame(SalesInvoiceWriteResult::TaxCalculationFailure, $this->create($bad, [$this->line(1, self::TAX_ACTIVE, '1', '0.00000001')]));

        $finalized = $this->invoiceId(2);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->create($finalized, [$this->line(2)]));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $finalized));
        self::assertSame(SalesInvoiceStatus::Finalized, $this->read($finalized)?->status());
        self::assertSame(SalesInvoiceWriteResult::InvalidState, $this->app->make(AddSalesInvoiceLine::class)->execute($this->admin(self::A), $finalized, $this->line(3)));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CancelSalesInvoice::class)->execute($this->admin(self::A), $finalized));
        self::assertSame(SalesInvoiceStatus::Cancelled, $this->read($finalized)?->status());
        self::assertTrue(class_exists('App\\Application\\Sales\\PostSalesInvoice'));
        self::assertFalse(class_exists('App\\Application\\Sales\\MarkSalesInvoicePaid'));
    }

    public function test_reverse_charge_create_uses_explicit_code_supply_date_and_immutable_party_snapshots(): void
    {
        AdministrationRecord::query()->whereKey(self::A)->update(['organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL']);
        RelationRecord::query()->whereKey($this->relation(self::CUSTOMER_A))->update(['vat_identification_number' => 'DE123456789', 'fiscal_jurisdiction' => 'DE']);
        $id = $this->invoiceId(20);

        $result = $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $id, new CustomerId(new Uuid(self::CUSTOMER_A)), $this->addressId(), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), [$this->line(20, self::TAX_EU_SERVICE, amount: '250')], new SupplyDate(new DateTimeImmutable('2026-08-20')));

        self::assertSame(SalesInvoiceWriteResult::Success, $result);
        $invoice = $this->read($id);
        self::assertSame('reverse_charge_eu_service', $invoice?->lines()[0]->taxSnapshot()?->treatment()->value);
        self::assertSame('eu_services', $invoice?->lines()[0]->taxSnapshot()?->vatReturnClassification()->value);
        self::assertSame('service', $invoice?->lines()[0]->taxSnapshot()?->icpClassification()->value);
        self::assertSame('0', $invoice?->lines()[0]->taxSnapshot()?->taxRate()->value());
        self::assertSame('DE123456789', $invoice?->customerFiscalSnapshot()?->vatIdentificationNumber()?->toString());
        self::assertSame('NL123456789B01', $invoice?->supplierFiscalSnapshot()?->vatIdentificationNumber()?->toString());
        self::assertSame('2026-08-20', $invoice?->supplyDate()?->value()->format('Y-m-d'));
    }

    public function test_reverse_charge_missing_snapshot_data_is_typed_and_writes_nothing(): void
    {
        AdministrationRecord::query()->whereKey(self::A)->update(['organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL']);
        RelationRecord::query()->whereKey($this->relation(self::CUSTOMER_A))->update(['fiscal_jurisdiction' => 'DE']);

        self::assertSame(SalesInvoiceWriteResult::CustomerVatIdMissing, $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $this->invoiceId(21), new CustomerId(new Uuid(self::CUSTOMER_A)), $this->addressId(), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), [$this->line(21, self::TAX_EU_SERVICE)], new SupplyDate(new DateTimeImmutable('2026-08-20'))));
        self::assertDatabaseMissing('sales_invoices', ['id' => $this->invoiceId(21)->toString()]);

        RelationRecord::query()->whereKey($this->relation(self::CUSTOMER_A))->update(['vat_identification_number' => 'DE123456789']);
        self::assertSame(SalesInvoiceWriteResult::SupplyDateMissing, $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $this->invoiceId(22), new CustomerId(new Uuid(self::CUSTOMER_A)), $this->addressId(), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), [$this->line(22, self::TAX_EU_SERVICE)]));
        self::assertDatabaseMissing('sales_invoices', ['id' => $this->invoiceId(22)->toString()]);
    }

    /** @param list<SalesInvoiceLineInput> $lines */
    private function create(SalesInvoiceId $id, array $lines = []): SalesInvoiceWriteResult
    {
        return $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $id, new CustomerId(new Uuid(self::CUSTOMER_A)), $this->addressId(), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), $lines);
    }

    private function read(SalesInvoiceId $id): ?SalesInvoice
    {
        return $this->app->make(SalesInvoiceReadRepository::class)->findForAdministration($this->admin(self::A), $id);
    }

    private function line(int $id, string $taxId = self::TAX_ACTIVE, string $quantity = '2', string $amount = '10'): SalesInvoiceLineInput
    {
        return new SalesInvoiceLineInput($this->lineId($id), new LineDescription('Service'), new Quantity($quantity), new Money($amount, new Currency('EUR')), new TaxCodeId(new Uuid($taxId)));
    }

    private function tenant(string $administration, string $customer, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $administration, 'code' => 'APP-'.$suffix, 'name' => 'Application '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
        RelationRecord::query()->create(['id' => $this->relation($customer), 'administration_id' => $administration, 'code' => 'REL-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true]);
        CustomerRecord::query()->create(['id' => $customer, 'administration_id' => $administration, 'relation_id' => $this->relation($customer), 'customer_number' => 'C-'.$suffix, 'active' => true]);
    }

    private function tax(string $id, string $code, string $rate, string $direction, string $status, ?string $treatment = null, ?string $vat = null, string $icp = 'none'): void
    {
        TaxCodeRecord::query()->create(['id' => $id, 'administration_id' => self::A, 'code' => $code, 'name' => $code.' name', 'rate' => $rate, 'direction' => $direction, 'status' => $status, 'treatment' => $treatment ?? ($rate === '0' ? 'zero_rated' : 'domestic_standard'), 'vat_return_classification' => $vat ?? ($rate === '0' ? 'domestic_zero_rated' : 'domestic_standard'), 'icp_classification' => $icp]);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function addressId(): AddressId
    {
        return new AddressId(new Uuid(self::ADDRESS_A));
    }

    private function invoiceId(int $id): SalesInvoiceId
    {
        return new SalesInvoiceId(new Uuid(sprintf('7e000000-0000-4000-8000-%012d', $id)));
    }

    private function lineId(int $id): SalesInvoiceLineId
    {
        return new SalesInvoiceLineId(new Uuid(sprintf('7f000000-0000-4000-8000-%012d', $id)));
    }

    private function relation(string $customer): string
    {
        return str_replace('300000-0000-4000', '350000-0000-4000', $customer);
    }
}

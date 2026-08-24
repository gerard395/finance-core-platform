<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Sales\SalesInvoiceReadinessChecker;
use App\Application\Sales\SalesInvoiceReadinessStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\Services\TaxCalculation;
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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesInvoiceReadinessCheckerTest extends TestCase
{
    public function test_ready_invoice_uses_exact_tax_calculation_for_totals(): void
    {
        $result = (new SalesInvoiceReadinessChecker(new TaxCalculation))->check($this->invoice('100', '21'));

        self::assertSame(SalesInvoiceReadinessStatus::Ready, $result->status());
        self::assertSame('100', $result->netTotal()?->amount());
        self::assertSame('21', $result->taxTotal()?->amount());
        self::assertSame('121', $result->grossTotal()?->amount());
    }

    public function test_missing_snapshots_and_nonrepresentable_tax_are_typed_failures(): void
    {
        $checker = new SalesInvoiceReadinessChecker(new TaxCalculation);
        self::assertSame(SalesInvoiceReadinessStatus::MissingCustomerSnapshot, $checker->check($this->invoice('100', '21', customer: false))->status());
        self::assertSame(SalesInvoiceReadinessStatus::MissingInvoiceAddress, $checker->check($this->invoice('100', '21', address: false))->status());
        self::assertSame(SalesInvoiceReadinessStatus::MissingTaxSnapshot, $checker->check($this->invoice('100', '21', tax: false))->status());
        self::assertSame(SalesInvoiceReadinessStatus::TaxCalculationFailed, $checker->check($this->invoice('0.00000001', '21.1234'))->status());
    }

    public function test_snapshots_are_immutable_value_objects_and_reconstitution_preserves_them(): void
    {
        $invoice = $this->invoice('100', '21');
        $restored = SalesInvoice::reconstitute(
            $invoice->id(), $invoice->number(), $invoice->administrationId(), $invoice->customerId(), $invoice->currency(),
            $invoice->invoiceDate(), $invoice->dueDate(), null, SalesInvoiceStatus::Finalized, $invoice->lines(),
            $invoice->customerSnapshot(), $invoice->invoiceAddressSnapshot(),
        );

        self::assertSame($invoice->customerSnapshot(), $restored->customerSnapshot());
        self::assertSame($invoice->invoiceAddressSnapshot(), $restored->invoiceAddressSnapshot());
        self::assertSame($invoice->lines()[0]->taxSnapshot(), $restored->lines()[0]->taxSnapshot());
    }

    public function test_tax_snapshot_survives_catalogue_rate_change_and_deactivation(): void
    {
        $taxCode = new TaxCode(
            new TaxCodeId($this->uuid('5')), new TaxCodeCode('VATOUT'), new TaxCodeName('Output tax'),
            new TaxRate('21'), TaxPostingDirection::Output, TaxCodeStatus::Active,
        );
        $snapshot = SalesTaxSnapshot::fromTaxCode($taxCode);

        $taxCode->changeRate(new TaxRate('19.5'));
        $taxCode->deactivate();

        self::assertSame('21', $snapshot->taxRate()->value());
        self::assertSame('VATOUT', $snapshot->taxCode()->value());
        self::assertSame(TaxPostingDirection::Output, $snapshot->direction());
    }

    public function test_reverse_charge_readiness_requires_historical_party_context_and_supply_date(): void
    {
        $invoice = $this->invoice('250', '0');
        $line = $invoice->lines()[0];
        $international = new SalesInvoice(
            $invoice->id(), $invoice->number(), $invoice->administrationId(), $invoice->customerId(), $invoice->currency(),
            $invoice->invoiceDate(), $invoice->dueDate(), null, SalesInvoiceStatus::Draft, $invoice->customerSnapshot(), $invoice->invoiceAddressSnapshot(),
            new SalesCustomerFiscalSnapshot($invoice->customerSnapshot()->relationId(), new VatIdentificationNumber('DE123456789'), new CountryCode('DE')),
            new SalesSupplierFiscalSnapshot($invoice->administrationId(), new VatIdentificationNumber('NL123456789B01'), new CountryCode('NL')),
            new SupplyDate(new DateTimeImmutable('2026-08-20')),
        );
        $international->addLine(new SalesInvoiceLine($line->id(), $line->description(), $line->quantity(), $line->unitPrice(), new SalesTaxSnapshot(new TaxCodeId($this->uuid('5')), new TaxCodeCode('EUSERVICE'), new TaxCodeName('EU service'), new TaxRate('0'), TaxPostingDirection::Output, TaxTreatment::ReverseChargeEuService, VatReturnClassification::EuServices, IcpClassification::Service)));

        $result = (new SalesInvoiceReadinessChecker(new TaxCalculation))->check($international);

        self::assertSame(SalesInvoiceReadinessStatus::Ready, $result->status());
        self::assertSame('250', $result->netTotal()?->amount());
        self::assertSame('0', $result->taxTotal()?->amount());
        self::assertSame('DE123456789', $international->customerFiscalSnapshot()?->vatIdentificationNumber()?->toString());
        self::assertSame('NL123456789B01', $international->supplierFiscalSnapshot()?->vatIdentificationNumber()?->toString());
        self::assertSame('2026-08-20', $international->supplyDate()?->value()->format('Y-m-d'));
        self::assertSame(IcpClassification::Service, $international->lines()[0]->taxSnapshot()?->icpClassification());
    }

    private function invoice(string $amount, string $rate, bool $customer = true, bool $address = true, bool $tax = true): SalesInvoice
    {
        $customerId = new CustomerId($this->uuid('2'));
        $invoice = new SalesInvoice(
            new SalesInvoiceId($this->uuid('1')), new SalesInvoiceNumber('F000001'), new AdministrationId($this->uuid('3')),
            $customerId, new Currency('EUR'), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-20'), null,
            SalesInvoiceStatus::Draft, $customer ? $this->customer($customerId) : null, $address ? $this->address() : null,
        );
        $invoice->addLine(new SalesInvoiceLine(
            new SalesInvoiceLineId($this->uuid('4')), new LineDescription('Service'), new Quantity('1'), new Money($amount, new Currency('EUR')),
            $tax ? new SalesTaxSnapshot(new TaxCodeId($this->uuid('5')), new TaxCodeCode('VATOUT'), new TaxCodeName('Output tax'), new TaxRate($rate), TaxPostingDirection::Output) : null,
        ));

        return $invoice;
    }

    private function customer(CustomerId $customerId): SalesCustomerSnapshot
    {
        return new SalesCustomerSnapshot($customerId, new RelationId($this->uuid('6')), new CustomerNumber('C000001'), new DisplayName('Historical customer'));
    }

    private function address(): SalesAddressSnapshot
    {
        return new SalesAddressSnapshot(new AddressId($this->uuid('7')), AddressType::Invoice, new AddressLine('Main street 1'), null, new PostalCode('1234 AB'), new City('Amsterdam'), new CountryCode('NL'));
    }

    private function uuid(string $prefix): Uuid
    {
        return new Uuid($prefix.'0000000-0000-4000-8000-000000000001');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\SalesCreditInvoiceReadRepository;
use App\Application\Sales\SalesDocumentIssuer;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentIssuerReadiness;
use App\Application\Sales\SalesDocumentRenderModel;
use App\Application\Sales\SalesDocumentRenderModelBuilder;
use App\Application\Sales\SalesDocumentSource;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\IcpClassification;
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
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\Services\SalesFiscalWordingPolicy;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesDocumentRenderModelBuilderTest extends TestCase
{
    public function test_mixed_invoice_uses_historical_fiscal_truth_and_treatment_specific_wording(): void
    {
        $invoice = $this->invoice([
            $this->invoiceLine(1, '100', TaxTreatment::DomesticStandard, '21', VatReturnClassification::DomesticStandard),
            $this->invoiceLine(2, '200', TaxTreatment::ReverseChargeEuService, '0', VatReturnClassification::EuServices, IcpClassification::Service),
        ]);
        $model = $this->builder(invoice: $invoice)->build($this->admin(), SalesDocumentSource::invoice($invoice->id()));

        self::assertInstanceOf(SalesDocumentRenderModel::class, $model);
        self::assertSame('NL123456789B01', $model->content['supplier_fiscal']['vat_id']);
        self::assertNotSame('NL999999999B99', $model->content['supplier_fiscal']['vat_id']);
        self::assertSame('2026-08-20', $model->content['document']['supply_date']);
        self::assertSame(['domestic_standard', 'reverse_charge_eu_service'], array_column($model->content['tax_summary'], 'treatment'));
        self::assertSame('vat_reverse_charged', $model->content['lines'][1]['wording']);
        self::assertSame('0', $model->content['lines'][1]['tax']);
        self::assertSame(['300', '21', '321'], array_values($model->content['totals']));
    }

    #[DataProvider('zeroTreatmentProvider')]
    public function test_zero_amount_treatments_remain_distinct_and_only_reverse_charge_has_reverse_wording(TaxTreatment $treatment, VatReturnClassification $classification, string $expectedWording): void
    {
        $invoice = $this->invoice([$this->invoiceLine(1, '100', $treatment, '0', $classification, $treatment === TaxTreatment::ReverseChargeEuService ? IcpClassification::Service : IcpClassification::None)]);
        $model = $this->builder(invoice: $invoice)->build($this->admin(), SalesDocumentSource::invoice($invoice->id()));
        self::assertInstanceOf(SalesDocumentRenderModel::class, $model);
        self::assertSame($treatment->value, $model->content['tax_summary'][0]['treatment']);
        self::assertSame($expectedWording, $model->content['tax_summary'][0]['wording']);
    }

    /** @return iterable<string, array{TaxTreatment, VatReturnClassification, string}> */
    public static function zeroTreatmentProvider(): iterable
    {
        yield 'BTW0' => [TaxTreatment::ZeroRated, VatReturnClassification::DomesticZeroRated, 'none'];
        yield 'EU service' => [TaxTreatment::ReverseChargeEuService, VatReturnClassification::EuServices, 'vat_reverse_charged'];
        yield 'exempt' => [TaxTreatment::Exempt, VatReturnClassification::Exempt, 'none'];
        yield 'outside scope' => [TaxTreatment::OutsideScope, VatReturnClassification::OutsideScope, 'none'];
    }

    public function test_credit_uses_source_invoice_tax_snapshot_and_historical_source_reference(): void
    {
        $invoice = $this->invoice([$this->invoiceLine(1, '100', TaxTreatment::ReverseChargeEuService, '0', VatReturnClassification::EuServices, IcpClassification::Service)]);
        $credit = SalesCreditInvoice::reconstitute(new SalesCreditInvoiceId($this->uuid(80)), new SalesCreditInvoiceNumber('C000001'), $this->admin(), $this->customerId(), new Currency('EUR'), new DateTimeImmutable('2026-08-25'), $invoice->id(), SalesCreditInvoiceStatus::Finalized, [new SalesCreditInvoiceLine(new SalesCreditInvoiceLineId($this->uuid(81)), new LineDescription('Service'), new Quantity('1'), new Money('100', new Currency('EUR')))], $this->customer(), $this->address(), $this->customerFiscal(), $this->supplierFiscal(), new SupplyDate(new DateTimeImmutable('2026-08-20')));
        $model = $this->builder($invoice, $credit)->build($this->admin(), SalesDocumentSource::creditInvoice($credit->id()));

        self::assertInstanceOf(SalesDocumentRenderModel::class, $model);
        self::assertSame('F000001', $model->content['document']['source_invoice_number']);
        self::assertSame('reverse_charge_eu_service', $model->content['lines'][0]['treatment']);
        self::assertSame('vat_reverse_charged', $model->content['lines'][0]['wording']);
    }

    private function builder(?SalesInvoice $invoice = null, ?SalesCreditInvoice $credit = null): SalesDocumentRenderModelBuilder
    {
        $quotations = $this->createStub(QuotationReadRepository::class);
        $invoices = $this->createStub(SalesInvoiceReadRepository::class);
        $invoices->method('findForAdministration')->willReturn($invoice);
        $credits = $this->createStub(SalesCreditInvoiceReadRepository::class);
        $credits->method('findForAdministration')->willReturn($credit);
        $issuer = $this->createStub(SalesDocumentIssuerReader::class);
        $issuer->method('readIssuer')->willReturn(new SalesDocumentIssuer('Current Demo B.V.', 'Demo', new AddressLine('Issuerstraat 1'), null, new PostalCode('1234AB'), new City('Amsterdam'), new CountryCode('NL'), new VatIdentificationNumber('NL999999999B99'), new CountryCode('NL'), '12345678', null, null, null, new Iban('NL91ABNA0417164300'), null, 'Current Demo B.V.'));

        return new SalesDocumentRenderModelBuilder($quotations, $invoices, $credits, $issuer, new SalesDocumentIssuerReadiness($issuer), new TaxCalculation, new SalesFiscalWordingPolicy);
    }

    /** @param list<SalesInvoiceLine> $lines */
    private function invoice(array $lines): SalesInvoice
    {
        return SalesInvoice::reconstitute(new SalesInvoiceId($this->uuid(1)), new SalesInvoiceNumber('F000001'), $this->admin(), $this->customerId(), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-21'), null, SalesInvoiceStatus::Finalized, $lines, $this->customer(), $this->address(), $this->customerFiscal(), $this->supplierFiscal(), new SupplyDate(new DateTimeImmutable('2026-08-20')));
    }

    private function invoiceLine(int $id, string $amount, TaxTreatment $treatment, string $rate, VatReturnClassification $classification, IcpClassification $icp = IcpClassification::None): SalesInvoiceLine
    {
        $tax = new SalesTaxSnapshot(new TaxCodeId($this->uuid(20 + $id)), new TaxCodeCode('TAX'.$id), new TaxCodeName('Tax '.$id), new TaxRate($rate), TaxPostingDirection::Output, $treatment, $classification, $icp);

        return new SalesInvoiceLine(new SalesInvoiceLineId($this->uuid(10 + $id)), new LineDescription($id === 1 ? 'Service' : 'Second service'), new Quantity('1'), new Money($amount, new Currency('EUR')), $tax);
    }

    private function customer(): SalesCustomerSnapshot
    {
        return new SalesCustomerSnapshot($this->customerId(), new RelationId($this->uuid(3)), new CustomerNumber('C000001'), new DisplayName('Customer'));
    }

    private function address(): SalesAddressSnapshot
    {
        return new SalesAddressSnapshot(new AddressId($this->uuid(4)), AddressType::Invoice, new AddressLine('Klantstraat 1'), null, new PostalCode('1000AA'), new City('Utrecht'), new CountryCode('NL'));
    }

    private function customerFiscal(): SalesCustomerFiscalSnapshot
    {
        return new SalesCustomerFiscalSnapshot(new RelationId($this->uuid(3)), new VatIdentificationNumber('DE123456789'), new CountryCode('DE'));
    }

    private function supplierFiscal(): SalesSupplierFiscalSnapshot
    {
        return new SalesSupplierFiscalSnapshot($this->admin(), new VatIdentificationNumber('NL123456789B01'), new CountryCode('NL'));
    }

    private function customerId(): CustomerId
    {
        return new CustomerId($this->uuid(2));
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId($this->uuid(9));
    }

    private function uuid(int $suffix): Uuid
    {
        return new Uuid(sprintf('a1000000-0000-4000-8000-%012d', $suffix));
    }
}

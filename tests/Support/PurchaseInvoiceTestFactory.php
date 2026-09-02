<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;

final class PurchaseInvoiceTestFactory
{
    public static function line(string $id, string $quantity = '1', string $unit = '10', string $rate = '0'): PurchaseInvoiceLine
    {
        $currency = new Currency('EUR');
        $net = (new Money($unit, $currency))->multiply($quantity);
        $tax = $net->multiply($rate === '21' ? '0.21' : ($rate === '9' ? '0.09' : '0'));

        return new PurchaseInvoiceLine(new PurchaseInvoiceLineId(new Uuid($id)), new LineDescription('Purchased goods'), new Quantity($quantity), new Money($unit, $currency), new PurchaseAccountSnapshot(new LedgerAccountId(new Uuid('aaaaaaaa-0000-4000-8000-000000000001')), new LedgerAccountCode('4000'), new LedgerAccountName('Expense'), LedgerAccountType::Expense), PurchaseTaxSnapshot::legacy(new TaxCodeId(new Uuid('bbbbbbbb-0000-4000-8000-000000000001')), new TaxCodeCode('INBTW'.$rate), new TaxCodeName('Input VAT'), new TaxRate($rate), TaxPostingDirection::Input, $rate === '0' ? TaxTreatment::ZeroRated : ($rate === '9' ? TaxTreatment::DomesticReduced : TaxTreatment::DomesticStandard), $rate === '0' ? VatReturnClassification::DomesticZeroRated : ($rate === '9' ? VatReturnClassification::DomesticReduced : VatReturnClassification::DomesticStandard), IcpClassification::None), $net, $tax, $net->add($tax));
    }

    /** @param list<PurchaseInvoiceLine> $lines */
    public static function invoice(string $id = '550e8400-e29b-41d4-a716-446655440000', PurchaseInvoiceStatus $status = PurchaseInvoiceStatus::Draft, array $lines = []): PurchaseInvoice
    {
        $finalized = in_array($status, [PurchaseInvoiceStatus::Finalized, PurchaseInvoiceStatus::Posted], true);

        return new PurchaseInvoice(new PurchaseInvoiceId(new Uuid($id)), new SupplierInvoiceNumber('PINV-001'), new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')), new PurchaseSupplierSnapshot(new SupplierId(new Uuid('550e8400-e29b-41d4-a716-446655440002')), new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440003')), new SupplierNumber('S000001'), new DisplayName('Supplier'), null, new CountryCode('NL')), new Currency('EUR'), new DateTimeImmutable('2026-07-15'), new DateTimeImmutable('2026-07-16'), null, new DateTimeImmutable('2026-07-16'), new DateTimeImmutable('2026-08-14'), new PurchaseDocumentAddress(new AddressLine('Street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), $status, $lines, $finalized ? new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440004')) : null, $finalized ? new DateTimeImmutable('2026-07-17 10:00:00') : null);
    }
}

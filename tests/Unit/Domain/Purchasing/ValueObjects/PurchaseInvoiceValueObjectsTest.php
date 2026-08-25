<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\ValueObjects;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PurchaseInvoiceValueObjectsTest extends TestCase
{
    public function test_supplier_invoice_number_only_canonicalizes_boundary_whitespace_and_preserves_case_and_punctuation(): void
    {
        $number = new SupplierInvoiceNumber(" \tAb c-01/ß\u{00A0}");

        self::assertSame('Ab c-01/ß', $number->canonical());
        self::assertFalse($number->equals(new SupplierInvoiceNumber('ab c-01/ß')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_supplier_invoice_number_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierInvoiceNumber($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => ['   '];
        yield 'line break' => ["INV\n1"];
        yield 'control' => ["INV\x001"];
        yield 'too long' => [str_repeat('é', 129)];
    }

    public function test_international_input_tax_snapshot_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PurchaseTaxSnapshot(
            new TaxCodeId(new Uuid('bbbbbbbb-0000-4000-8000-000000000099')),
            new TaxCodeCode('INEU'),
            new TaxCodeName('EU acquisition'),
            new TaxRate('21'),
            TaxPostingDirection::Input,
            TaxTreatment::ReverseChargeEuService,
            VatReturnClassification::EuServices,
            IcpClassification::Service,
        );
    }
}

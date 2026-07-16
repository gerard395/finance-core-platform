<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\Entities;

use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PurchaseInvoiceLineTest extends TestCase
{
    public function test_it_is_constructed_with_shared_values_and_exact_line_total(): void
    {
        $id = new PurchaseInvoiceLineId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $description = new LineDescription('Purchased goods');
        $quantity = new Quantity('2.5');
        $unitPrice = new Money('12.40', new Currency('EUR'));
        $line = new PurchaseInvoiceLine($id, $description, $quantity, $unitPrice);

        self::assertSame($id, $line->id());
        self::assertSame($description, $line->description());
        self::assertSame($quantity, $line->quantity());
        self::assertSame($unitPrice, $line->unitPrice());
        self::assertSame('31', $line->lineTotal()->amount());
        self::assertSame('EUR', $line->lineTotal()->currency()->code());
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quantity('0');
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createLine('-0.01');
    }

    private function createLine(string $unitPrice): PurchaseInvoiceLine
    {
        return new PurchaseInvoiceLine(
            new PurchaseInvoiceLineId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new LineDescription('Purchased goods'),
            new Quantity('2'),
            new Money($unitPrice, new Currency('EUR')),
        );
    }
}

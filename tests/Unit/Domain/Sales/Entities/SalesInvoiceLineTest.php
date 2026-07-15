<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\ValueObjects\LineDescription;
use App\Domain\Sales\ValueObjects\Quantity;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesInvoiceLineTest extends TestCase
{
    public function test_valid_line_and_line_total(): void
    {
        $line = $this->createLine('2.5', '12.40');

        self::assertSame('550e8400-e29b-41d4-a716-446655440010', $line->id()->toString());
        self::assertSame('Product delivery', $line->description()->value());
        self::assertSame('2.5', $line->quantity()->value());
        self::assertSame('12.4', $line->unitPrice()->amount());
        self::assertSame('31', $line->lineTotal()->amount());
        self::assertSame($line->unitPrice()->currency(), $line->lineTotal()->currency());
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Quantity('0');
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createLine('1', '-1');
    }

    private function createLine(string $quantity = '1', string $unitPrice = '10'): SalesInvoiceLine
    {
        return new SalesInvoiceLine(
            new SalesInvoiceLineId(new Uuid('550e8400-e29b-41d4-a716-446655440010')),
            new LineDescription('Product delivery'),
            new Quantity($quantity),
            new Money($unitPrice, new Currency('EUR')),
        );
    }
}

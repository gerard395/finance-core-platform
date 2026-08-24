<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\OrderInvoiceQuantityBalance;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use DomainException;
use PHPUnit\Framework\TestCase;

final class OrderInvoiceQuantityBalanceTest extends TestCase
{
    public function test_exact_non_negative_arithmetic_uses_quantity_precision_without_floats(): void
    {
        $balance = OrderInvoiceQuantityBalance::fromQuantity(new Quantity('10.12345678'))
            ->subtract(new Quantity('4.02345678'))
            ->add(new Quantity('0.9'));

        self::assertSame('7', $balance->value());
        self::assertFalse($balance->isLessThan(new Quantity('7')));
        self::assertTrue($balance->isLessThan(new Quantity('7.00000001')));
    }

    public function test_balance_rejects_negative_result(): void
    {
        $this->expectException(DomainException::class);
        OrderInvoiceQuantityBalance::zero()->subtract(new Quantity('0.1'));
    }
}

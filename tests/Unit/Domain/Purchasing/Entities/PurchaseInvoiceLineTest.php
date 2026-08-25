<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\Entities;

use PHPUnit\Framework\TestCase;
use Tests\Support\PurchaseInvoiceTestFactory;

final class PurchaseInvoiceLineTest extends TestCase
{
    public function test_line_preserves_account_tax_and_exact_amount_snapshots(): void
    {
        $line = PurchaseInvoiceTestFactory::line('dddddddd-0000-4000-8000-000000000001', '2.5', '12.4', '21');
        self::assertSame('31', $line->net()->amount());
        self::assertSame('6.51', $line->taxAmount()->amount());
        self::assertSame('37.51', $line->gross()->amount());
        self::assertSame('expense', $line->account()->type->value);
        self::assertSame('input', $line->tax()->direction->value);
    }
}

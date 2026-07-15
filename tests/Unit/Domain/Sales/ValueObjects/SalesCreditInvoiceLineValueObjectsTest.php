<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class SalesCreditInvoiceLineValueObjectsTest extends TestCase
{
    public function test_sales_credit_invoice_line_id_follows_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440010');
        $id = new SalesCreditInvoiceLineId($uuid);

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new SalesCreditInvoiceLineId(new Uuid($uuid->toString()))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }
}

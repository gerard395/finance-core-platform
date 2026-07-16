<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\ValueObjects;

use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class PurchaseInvoiceIdTest extends TestCase
{
    public function test_it_wraps_a_uuid_and_uses_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new PurchaseInvoiceId($uuid);

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new PurchaseInvoiceId(new Uuid($uuid->toString()))));
        self::assertFalse($id->equals(new PurchaseInvoiceId(new Uuid('123e4567-e89b-42d3-a456-426614174000'))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }
}

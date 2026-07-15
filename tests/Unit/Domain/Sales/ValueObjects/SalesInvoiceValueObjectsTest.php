<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesInvoiceValueObjectsTest extends TestCase
{
    public function test_id_and_number_follow_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new SalesInvoiceId($uuid);
        $number = new SalesInvoiceNumber('inv-001');

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new SalesInvoiceId(new Uuid($uuid->toString()))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame('INV-001', $number->value());
        self::assertTrue($number->equals(new SalesInvoiceNumber('INV-001')));
        self::assertSame('INV-001', (string) $number);
    }

    public function test_invalid_number_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SalesInvoiceNumber('I');
    }
}

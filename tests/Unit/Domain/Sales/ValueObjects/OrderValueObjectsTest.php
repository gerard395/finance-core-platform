<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderValueObjectsTest extends TestCase
{
    public function test_id_and_number_follow_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new OrderId($uuid);
        $number = new OrderNumber('ord-001');

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new OrderId(new Uuid($uuid->toString()))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame('ORD-001', $number->value());
        self::assertTrue($number->equals(new OrderNumber('ORD-001')));
        self::assertSame('ORD-001', (string) $number);
    }

    public function test_invalid_number_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OrderNumber('O');
    }
}

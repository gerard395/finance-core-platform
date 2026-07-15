<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuotationValueObjectsTest extends TestCase
{
    public function test_id_and_number_follow_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new QuotationId($uuid);
        $number = new QuotationNumber('quo-001');

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new QuotationId(new Uuid($uuid->toString()))));
        self::assertSame('QUO-001', $number->value());
        self::assertTrue($number->equals(new QuotationNumber('QUO-001')));
    }

    public function test_invalid_number_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QuotationNumber('Q');
    }
}

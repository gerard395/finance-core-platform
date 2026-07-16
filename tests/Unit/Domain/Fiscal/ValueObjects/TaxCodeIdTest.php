<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class TaxCodeIdTest extends TestCase
{
    public function test_it_wraps_a_uuid_and_uses_value_object_semantics(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new TaxCodeId($uuid);

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new TaxCodeId(new Uuid($uuid->toString()))));
        self::assertFalse($id->equals(new TaxCodeId(new Uuid('123e4567-e89b-42d3-a456-426614174000'))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }
}

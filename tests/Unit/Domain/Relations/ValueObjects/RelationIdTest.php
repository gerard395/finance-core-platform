<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class RelationIdTest extends TestCase
{
    public function test_it_wraps_and_returns_the_same_uuid(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame($uuid, new RelationId($uuid)->uuid());
    }

    public function test_equality_and_string_representations_use_the_uuid_value(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new RelationId($uuid);

        self::assertTrue($id->equals(new RelationId(new Uuid('550E8400-E29B-41D4-A716-446655440000'))));
        self::assertFalse($id->equals(new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440001'))));
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }
}

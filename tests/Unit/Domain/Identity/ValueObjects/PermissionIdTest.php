<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class PermissionIdTest extends TestCase
{
    public function test_it_wraps_and_returns_the_same_uuid(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame($uuid, new PermissionId($uuid)->uuid());
    }

    public function test_equality_uses_the_uuid_value(): void
    {
        $id = new PermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));

        self::assertTrue($id->equals(new PermissionId(new Uuid('550E8400-E29B-41D4-A716-446655440000'))));
        self::assertFalse($id->equals(new PermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440001'))));
    }

    public function test_string_representations_match_the_uuid(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');
        $id = new PermissionId($uuid);

        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Identity;

use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_it_accepts_a_valid_lowercase_uuid(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->toString());
    }

    public function test_it_normalizes_a_valid_uppercase_uuid_to_lowercase(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->toString());
    }

    public function test_it_accepts_uuid_version_one(): void
    {
        $uuid = new Uuid('550e8400-e29b-11d4-a716-446655440000');

        self::assertSame('550e8400-e29b-11d4-a716-446655440000', $uuid->toString());
    }

    public function test_it_accepts_uuid_version_seven(): void
    {
        $uuid = new Uuid('01890f3e-70cc-7cc3-8e9a-7d5c9f6b4a21');

        self::assertSame('01890f3e-70cc-7cc3-8e9a-7d5c9f6b4a21', $uuid->toString());
    }

    public function test_equal_uuids_compare_as_equal(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $sameUuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');

        self::assertTrue($uuid->equals($sameUuid));
    }

    public function test_different_uuids_compare_as_unequal(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $otherUuid = new Uuid('550e8400-e29b-41d4-a716-446655440001');

        self::assertFalse($uuid->equals($otherUuid));
    }

    public function test_string_methods_return_the_canonical_value(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }

    public function test_it_rejects_an_invalid_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uuid('550e8400-e29b-41d4-a716-44665544000');
    }

    public function test_it_rejects_invalid_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uuid('550e8400-e29b-41d4-a716-44665544000g');
    }

    public function test_it_rejects_an_invalid_version(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uuid('550e8400-e29b-91d4-a716-446655440000');
    }

    public function test_it_rejects_an_invalid_variant(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uuid('550e8400-e29b-41d4-7716-446655440000');
    }

    public function test_it_rejects_the_nil_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uuid('00000000-0000-0000-0000-000000000000');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class AdministrationIdTest extends TestCase
{
    public function test_constructor_accepts_a_uuid(): void
    {
        $administrationId = new AdministrationId(
            new Uuid('550e8400-e29b-41d4-a716-446655440000'),
        );

        self::assertInstanceOf(Uuid::class, $administrationId->uuid());
    }

    public function test_uuid_returns_the_same_object(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $administrationId = new AdministrationId($uuid);

        self::assertSame($uuid, $administrationId->uuid());
    }

    public function test_equal_administration_ids_compare_as_equal(): void
    {
        $administrationId = new AdministrationId(
            new Uuid('550e8400-e29b-41d4-a716-446655440000'),
        );
        $sameAdministrationId = new AdministrationId(
            new Uuid('550E8400-E29B-41D4-A716-446655440000'),
        );

        self::assertTrue($administrationId->equals($sameAdministrationId));
    }

    public function test_string_representations_match_the_uuid(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');
        $administrationId = new AdministrationId($uuid);

        self::assertSame($uuid->toString(), $administrationId->toString());
        self::assertSame($uuid->toString(), (string) $administrationId);
    }

    public function test_different_administration_ids_compare_as_unequal(): void
    {
        $administrationId = new AdministrationId(
            new Uuid('550e8400-e29b-41d4-a716-446655440000'),
        );
        $otherAdministrationId = new AdministrationId(
            new Uuid('550e8400-e29b-41d4-a716-446655440001'),
        );

        self::assertFalse($administrationId->equals($otherAdministrationId));
    }
}

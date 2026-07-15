<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\OrganisationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class OrganisationIdTest extends TestCase
{
    public function test_constructor_accepts_a_uuid_and_returns_the_same_object(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $organisationId = new OrganisationId($uuid);

        self::assertSame($uuid, $organisationId->uuid());
    }

    public function test_equal_organisation_ids_compare_as_equal(): void
    {
        $organisationId = new OrganisationId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $sameOrganisationId = new OrganisationId(new Uuid('550E8400-E29B-41D4-A716-446655440000'));

        self::assertTrue($organisationId->equals($sameOrganisationId));
    }

    public function test_different_organisation_ids_compare_as_unequal(): void
    {
        $organisationId = new OrganisationId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $otherOrganisationId = new OrganisationId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));

        self::assertFalse($organisationId->equals($otherOrganisationId));
    }

    public function test_string_representations_match_the_uuid(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');
        $organisationId = new OrganisationId($uuid);

        self::assertSame($uuid->toString(), $organisationId->toString());
        self::assertSame($uuid->toString(), (string) $organisationId);
    }
}

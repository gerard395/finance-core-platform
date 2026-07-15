<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdministrationMembershipTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $membership = $this->createMembership();

        self::assertInstanceOf(AdministrationMembershipId::class, $membership->id());
        self::assertInstanceOf(UserId::class, $membership->userId());
        self::assertInstanceOf(AdministrationId::class, $membership->administrationId());
        self::assertSame('2026-01-01', $membership->validFrom()->format('Y-m-d'));
        self::assertSame('2026-12-31', $membership->validUntil()->format('Y-m-d'));
        self::assertTrue($membership->isActive());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $membership = $this->createMembership();

        $membership->deactivate();
        $membership->deactivate();
        self::assertFalse($membership->isActive());

        $membership->activate();
        $membership->activate();
        self::assertTrue($membership->isActive());
    }

    public function test_it_is_valid_during_the_inclusive_period_when_active(): void
    {
        $membership = $this->createMembership();

        self::assertTrue($membership->isValidAt(new DateTimeImmutable('2026-01-01')));
        self::assertTrue($membership->isValidAt(new DateTimeImmutable('2026-06-15')));
        self::assertTrue($membership->isValidAt(new DateTimeImmutable('2026-12-31')));
    }

    public function test_it_is_invalid_outside_the_period(): void
    {
        $membership = $this->createMembership();

        self::assertFalse($membership->isValidAt(new DateTimeImmutable('2025-12-31')));
        self::assertFalse($membership->isValidAt(new DateTimeImmutable('2027-01-01')));
    }

    public function test_it_is_invalid_during_the_period_when_inactive(): void
    {
        $membership = $this->createMembership();
        $membership->deactivate();

        self::assertFalse($membership->isValidAt(new DateTimeImmutable('2026-06-15')));
    }

    public function test_identity_and_relations_remain_unchanged_after_status_transitions(): void
    {
        $membership = $this->createMembership();
        $id = $membership->id();
        $userId = $membership->userId();
        $administrationId = $membership->administrationId();

        $membership->deactivate();
        $membership->activate();

        self::assertSame($id, $membership->id());
        self::assertSame($userId, $membership->userId());
        self::assertSame($administrationId, $membership->administrationId());
    }

    public function test_it_rejects_an_inverted_validity_period(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createMembership(
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-01-01'),
        );
    }

    private function createMembership(
        ?DateTimeImmutable $validFrom = null,
        ?DateTimeImmutable $validUntil = null,
    ): AdministrationMembership {
        return new AdministrationMembership(
            new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            true,
            $validFrom ?? new DateTimeImmutable('2026-01-01'),
            $validUntil ?? new DateTimeImmutable('2026-12-31'),
        );
    }
}

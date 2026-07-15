<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class MembershipRoleTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $assignment = $this->createAssignment();

        self::assertInstanceOf(MembershipRoleId::class, $assignment->id());
        self::assertInstanceOf(AdministrationMembershipId::class, $assignment->membershipId());
        self::assertInstanceOf(RoleId::class, $assignment->roleId());
        self::assertTrue($assignment->isActive());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $assignment = $this->createAssignment();

        $assignment->deactivate();
        $assignment->deactivate();
        self::assertFalse($assignment->isActive());

        $assignment->activate();
        $assignment->activate();
        self::assertTrue($assignment->isActive());
    }

    public function test_identity_and_references_remain_unchanged_after_status_transitions(): void
    {
        $assignment = $this->createAssignment();
        $id = $assignment->id();
        $membershipId = $assignment->membershipId();
        $roleId = $assignment->roleId();

        $assignment->deactivate();
        $assignment->activate();

        self::assertSame($id, $assignment->id());
        self::assertSame($membershipId, $assignment->membershipId());
        self::assertSame($roleId, $assignment->roleId());
    }

    private function createAssignment(): MembershipRole
    {
        return new MembershipRole(
            new MembershipRoleId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            true,
        );
    }
}

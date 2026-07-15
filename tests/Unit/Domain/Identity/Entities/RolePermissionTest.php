<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class RolePermissionTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $assignment = $this->createAssignment();

        self::assertInstanceOf(RolePermissionId::class, $assignment->id());
        self::assertInstanceOf(RoleId::class, $assignment->roleId());
        self::assertInstanceOf(PermissionId::class, $assignment->permissionId());
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
        $roleId = $assignment->roleId();
        $permissionId = $assignment->permissionId();

        $assignment->deactivate();
        $assignment->activate();

        self::assertSame($id, $assignment->id());
        self::assertSame($roleId, $assignment->roleId());
        self::assertSame($permissionId, $assignment->permissionId());
    }

    private function createAssignment(): RolePermission
    {
        return new RolePermission(
            new RolePermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new PermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            true,
        );
    }
}

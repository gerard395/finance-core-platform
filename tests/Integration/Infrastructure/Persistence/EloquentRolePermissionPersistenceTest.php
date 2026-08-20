<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentRolePermissionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_round_trip_as_domain_entities(): void
    {
        $role = $this->role();
        $permission = $this->permission();
        $roles = new EloquentRoleRepository;
        $permissions = new EloquentPermissionRepository;

        $roles->save($role);
        $permissions->save($permission);

        $savedRole = $roles->findById($role->id());
        $savedPermission = $permissions->findById($permission->id());
        self::assertInstanceOf(Role::class, $savedRole);
        self::assertInstanceOf(Permission::class, $savedPermission);
        self::assertNotInstanceOf(RoleRecord::class, $savedRole);
        self::assertNotInstanceOf(PermissionRecord::class, $savedPermission);
        self::assertSame('FINANCE_ADMIN', $savedRole->code()->toString());
        self::assertSame('Finance administrator', $savedRole->name()->toString());
        self::assertSame(RoleStatus::Active, $savedRole->status());
        self::assertSame('REPORTING.VIEW', $savedPermission->code()->toString());
        self::assertSame(PermissionStatus::Active, $savedPermission->status());
    }

    public function test_role_permission_assignment_round_trips_and_updates(): void
    {
        [$role, $permission] = $this->persistRoleAndPermission();
        $assignment = $this->assignment($role->id(), $permission->id());
        $repository = new EloquentRolePermissionRepository;

        $repository->save($assignment);
        $assignment->deactivate();
        $repository->save($assignment);

        $saved = $repository->findById($assignment->id());
        self::assertInstanceOf(RolePermission::class, $saved);
        self::assertNotInstanceOf(RolePermissionRecord::class, $saved);
        self::assertTrue($role->id()->equals($saved->roleId()));
        self::assertTrue($permission->id()->equals($saved->permissionId()));
        self::assertFalse($saved->isActive());
        self::assertSame(1, RolePermissionRecord::query()->count());
    }

    public function test_duplicate_role_permission_pair_is_rejected(): void
    {
        [$role, $permission] = $this->persistRoleAndPermission();
        $repository = new EloquentRolePermissionRepository;
        $repository->save($this->assignment($role->id(), $permission->id()));

        $this->expectException(QueryException::class);

        $repository->save(new RolePermission(
            new RolePermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440099')),
            $role->id(),
            $permission->id(),
            true,
        ));
    }

    public function test_assignment_with_unknown_role_is_rejected_by_foreign_key(): void
    {
        $permission = $this->permission();
        (new EloquentPermissionRepository)->save($permission);

        $this->expectException(QueryException::class);

        (new EloquentRolePermissionRepository)->save($this->assignment(
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-446655440098')),
            $permission->id(),
        ));
    }

    /** @return array{Role, Permission} */
    private function persistRoleAndPermission(): array
    {
        $role = $this->role();
        $permission = $this->permission();
        (new EloquentRoleRepository)->save($role);
        (new EloquentPermissionRepository)->save($permission);

        return [$role, $permission];
    }

    private function role(): Role
    {
        return new Role(
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-446655440010')),
            new RoleCode('finance_admin'),
            new RoleName('Finance administrator'),
            'Manages finance.',
            RoleStatus::Active,
        );
    }

    private function permission(): Permission
    {
        return new Permission(
            new PermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440011')),
            new PermissionCode('reporting.view'),
            new PermissionName('View reporting'),
            'Views reports.',
            PermissionStatus::Active,
        );
    }

    private function assignment(RoleId $roleId, PermissionId $permissionId): RolePermission
    {
        return new RolePermission(
            new RolePermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440012')),
            $roleId,
            $permissionId,
            true,
        );
    }
}

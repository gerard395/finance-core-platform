<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\RelationsRole;
use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Database\Seeders\RelationsAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RelationsAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_exact_relations_definitions_and_mappings_idempotently(): void
    {
        $provisioner = $this->app->make(RelationsAuthorizationProvisioner::class);

        $provisioner->provision();
        $firstPermissions = PermissionRecord::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray();
        $firstRoles = RoleRecord::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray();
        $firstAssignments = RolePermissionRecord::query()->orderBy('id')->get(['id', 'role_id', 'permission_id', 'active'])->toArray();

        self::assertCount(5, $firstPermissions);
        self::assertCount(3, $firstRoles);
        self::assertCount(9, $firstAssignments);
        self::assertSameCanonicalDefinitions();
        $this->assertMappings();
        $this->assertRepositoryRoundTrips();

        $provisioner->provision();

        self::assertSame($firstPermissions, PermissionRecord::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray());
        self::assertSame($firstRoles, RoleRecord::query()->orderBy('id')->get(['id', 'code', 'name'])->toArray());
        self::assertSame($firstAssignments, RolePermissionRecord::query()->orderBy('id')->get(['id', 'role_id', 'permission_id', 'active'])->toArray());
    }

    public function test_it_preserves_unrelated_identity_definitions(): void
    {
        $unrelatedPermission = new Permission(
            new PermissionId(new Uuid('28a54516-675a-42ce-9afc-c6c5f8db69a7')),
            new PermissionCode('REPORTING.VIEW'),
            new PermissionName('View reporting'),
            null,
            PermissionStatus::Active,
        );
        $unrelatedRole = new Role(
            new RoleId(new Uuid('7b39248b-9dd9-46ab-9325-5de97cd21391')),
            new RoleCode('FINANCE_ADMIN'),
            new RoleName('Finance administrator'),
            null,
            RoleStatus::Active,
        );
        (new EloquentPermissionRepository)->save($unrelatedPermission);
        (new EloquentRoleRepository)->save($unrelatedRole);

        $this->app->make(RelationsAuthorizationProvisioner::class)->provision();

        self::assertSame(6, PermissionRecord::query()->count());
        self::assertSame(4, RoleRecord::query()->count());
        self::assertTrue(PermissionRecord::query()->whereKey($unrelatedPermission->id()->toString())->exists());
        self::assertTrue(RoleRecord::query()->whereKey($unrelatedRole->id()->toString())->exists());
    }

    public function test_relations_authorization_seeder_is_reproducible(): void
    {
        $this->seed(RelationsAuthorizationSeeder::class);
        $this->seed(RelationsAuthorizationSeeder::class);

        self::assertSame(5, PermissionRecord::query()->count());
        self::assertSame(3, RoleRecord::query()->count());
        self::assertSame(9, RolePermissionRecord::query()->count());
    }

    private function assertSameCanonicalDefinitions(): void
    {
        foreach (RelationsPermission::cases() as $permission) {
            $this->assertDatabaseHas('permissions', [
                'id' => $permission->id()->toString(),
                'code' => $permission->code()->toString(),
                'name' => $permission->name()->toString(),
                'status' => PermissionStatus::Active->value,
            ]);
        }
        foreach (RelationsRole::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'id' => $role->id()->toString(),
                'code' => $role->code()->toString(),
                'name' => $role->name()->toString(),
                'status' => RoleStatus::Active->value,
            ]);
        }
    }

    private function assertMappings(): void
    {
        foreach (RelationsRole::cases() as $role) {
            $actual = RolePermissionRecord::query()
                ->where('role_id', $role->id()->toString())
                ->where('active', true)
                ->orderBy('permission_id')
                ->pluck('permission_id')
                ->all();
            $expected = array_map(static fn (RelationsPermission $permission): string => $permission->id()->toString(), $role->permissions());
            sort($expected);
            self::assertSame($expected, $actual);
        }
    }

    private function assertRepositoryRoundTrips(): void
    {
        $permissions = new EloquentPermissionRepository;
        foreach (RelationsPermission::cases() as $definition) {
            $permission = $permissions->findById($definition->id());
            self::assertNotNull($permission);
            self::assertTrue($definition->id()->equals($permission->id()));
            self::assertSame($definition->code()->toString(), $permission->code()->toString());
        }

        $roles = new EloquentRoleRepository;
        foreach (RelationsRole::cases() as $definition) {
            $role = $roles->findById($definition->id());
            self::assertNotNull($role);
            self::assertTrue($definition->id()->equals($role->id()));
            self::assertSame($definition->code()->toString(), $role->code()->toString());
        }
    }
}

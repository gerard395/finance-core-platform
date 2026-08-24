<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Definitions\SalesRole;
use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Database\Seeders\SalesAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class SalesAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_exact_definitions_and_mappings_idempotently(): void
    {
        $provisioner = $this->app->make(SalesAuthorizationProvisioner::class);
        $provisioner->provision();
        $permissions = PermissionRecord::query()->orderBy('id')->get(['id', 'code', 'name', 'status'])->toArray();
        $roles = RoleRecord::query()->orderBy('id')->get(['id', 'code', 'name', 'status'])->toArray();
        $assignments = RolePermissionRecord::query()->orderBy('id')->get(['id', 'role_id', 'permission_id', 'active'])->toArray();

        self::assertCount(9, $permissions);
        self::assertCount(4, $roles);
        self::assertCount(15, $assignments);
        $this->assertCanonicalDefinitionsAndMappings();

        $provisioner->provision();

        self::assertSame($permissions, PermissionRecord::query()->orderBy('id')->get(['id', 'code', 'name', 'status'])->toArray());
        self::assertSame($roles, RoleRecord::query()->orderBy('id')->get(['id', 'code', 'name', 'status'])->toArray());
        self::assertSame($assignments, RolePermissionRecord::query()->orderBy('id')->get(['id', 'role_id', 'permission_id', 'active'])->toArray());
    }

    public function test_it_preserves_unrelated_identity_data_and_seeder_is_reproducible(): void
    {
        $unrelated = new Permission(
            new PermissionId(new Uuid('28a54516-675a-42ce-9afc-c6c5f8db69a7')),
            new PermissionCode('REPORTING.VIEW'),
            new PermissionName('View reporting'),
            null,
            PermissionStatus::Active,
        );
        (new EloquentPermissionRepository)->save($unrelated);

        $this->seed(SalesAuthorizationSeeder::class);
        $this->seed(SalesAuthorizationSeeder::class);

        self::assertSame(10, PermissionRecord::query()->count());
        self::assertSame(4, RoleRecord::query()->count());
        self::assertSame(15, RolePermissionRecord::query()->count());
        self::assertTrue(PermissionRecord::query()->whereKey($unrelated->id()->toString())->exists());
    }

    public function test_ambiguous_identity_or_code_collision_is_rejected_transactionally(): void
    {
        $conflict = SalesPermission::View;
        (new EloquentPermissionRepository)->save(new Permission(
            new PermissionId(new Uuid('11111111-1111-4111-8111-111111111111')),
            $conflict->code(),
            new PermissionName('Conflicting view'),
            null,
            PermissionStatus::Active,
        ));

        try {
            $this->app->make(SalesAuthorizationProvisioner::class)->provision();
            self::fail('Ambiguous canonical identity must be rejected.');
        } catch (LogicException $exception) {
            self::assertSame('Permission definition is ambiguous for canonical code SALES.VIEW.', $exception->getMessage());
        }

        self::assertSame(1, PermissionRecord::query()->count());
        self::assertSame(0, RoleRecord::query()->count());
        self::assertSame(0, RolePermissionRecord::query()->count());
    }

    private function assertCanonicalDefinitionsAndMappings(): void
    {
        foreach (SalesPermission::cases() as $permission) {
            $this->assertDatabaseHas('permissions', ['id' => $permission->id()->toString(), 'code' => $permission->value, 'name' => $permission->name()->toString(), 'status' => 'active']);
        }
        foreach (SalesRole::cases() as $role) {
            $this->assertDatabaseHas('roles', ['id' => $role->id()->toString(), 'code' => $role->value, 'name' => $role->name()->toString(), 'status' => 'active']);
            $expected = array_map(static fn (SalesPermission $permission): string => $permission->id()->toString(), $role->permissions());
            sort($expected);
            self::assertSame($expected, RolePermissionRecord::query()->where('role_id', $role->id()->toString())->where('active', true)->orderBy('permission_id')->pluck('permission_id')->all());
        }
    }
}

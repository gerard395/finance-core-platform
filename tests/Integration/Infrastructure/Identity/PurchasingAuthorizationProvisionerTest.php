<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\PurchasingRole;
use App\Infrastructure\Identity\PurchasingAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRoleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchasingAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_exact_contract_idempotently_without_membership_assignments(): void
    {
        $provisioner = $this->app->make(PurchasingAuthorizationProvisioner::class);
        $provisioner->provision();
        $before = [PermissionRecord::all()->toArray(), RoleRecord::all()->toArray(), RolePermissionRecord::all()->toArray()];
        $provisioner->provision();

        self::assertSame($before, [PermissionRecord::all()->toArray(), RoleRecord::all()->toArray(), RolePermissionRecord::all()->toArray()]);
        self::assertSame(4, PermissionRecord::query()->count());
        self::assertSame(2, RoleRecord::query()->count());
        self::assertSame(5, RolePermissionRecord::query()->where('active', true)->count());
        self::assertSame(0, AdministrationMembershipRoleRecord::query()->count());
        foreach (PurchasingPermission::cases() as $permission) {
            $this->assertDatabaseHas('permissions', ['id' => $permission->id()->toString(), 'code' => $permission->value, 'status' => 'active']);
        }
        foreach (PurchasingRole::cases() as $role) {
            self::assertSame(
                array_map(static fn (PurchasingPermission $permission): string => $permission->id()->toString(), $role->permissions()),
                RolePermissionRecord::query()->where('role_id', $role->id()->toString())->orderBy('id')->get()->sortBy(fn ($row) => array_search($row->permission_id, array_map(static fn ($permission) => $permission->id()->toString(), $role->permissions()), true))->pluck('permission_id')->values()->all(),
            );
        }
    }
}

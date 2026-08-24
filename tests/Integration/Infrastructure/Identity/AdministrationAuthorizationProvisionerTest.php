<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\AdministrationRole;
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRoleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Database\Seeders\AdministrationAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class AdministrationAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_stable_definitions_idempotently_without_membership_assignment(): void
    {
        DB::table('permissions')->insert([
            'id' => '11111111-1111-4111-8111-111111111111', 'code' => 'REPORTING.VIEW',
            'name' => 'View Reporting', 'description' => null, 'status' => 'active',
        ]);

        $provisioner = $this->app->make(AdministrationAuthorizationProvisioner::class);
        $provisioner->provision();
        $first = [PermissionRecord::query()->count(), RoleRecord::query()->count(), RolePermissionRecord::query()->count()];

        $this->assertDatabaseHas('permissions', [
            'id' => AdministrationPermission::UpdateSettings->id()->toString(),
            'code' => AdministrationPermission::UpdateSettings->value,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('roles', [
            'id' => AdministrationRole::Manager->id()->toString(),
            'code' => AdministrationRole::Manager->value,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'id' => 'c0ac832e-bb21-4dbd-a44b-504aff94000f',
            'role_id' => AdministrationRole::Manager->id()->toString(),
            'permission_id' => AdministrationPermission::UpdateSettings->id()->toString(),
            'active' => true,
        ]);
        self::assertSame(0, AdministrationMembershipRoleRecord::query()->count());

        $provisioner->provision();
        self::assertSame($first, [PermissionRecord::query()->count(), RoleRecord::query()->count(), RolePermissionRecord::query()->count()]);
        $this->seed(AdministrationAuthorizationSeeder::class);
        self::assertSame($first, [PermissionRecord::query()->count(), RoleRecord::query()->count(), RolePermissionRecord::query()->count()]);
        $this->assertDatabaseHas('permissions', ['code' => 'REPORTING.VIEW']);
    }

    public function test_it_rejects_a_code_collision_without_partial_provisioning(): void
    {
        DB::table('permissions')->insert([
            'id' => '22222222-2222-4222-8222-222222222222',
            'code' => AdministrationPermission::UpdateSettings->value,
            'name' => 'Collision', 'description' => null, 'status' => 'active',
        ]);

        $this->expectException(LogicException::class);
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
    }
}

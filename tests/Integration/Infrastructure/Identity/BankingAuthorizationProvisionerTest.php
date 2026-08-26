<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Application\Identity\AuthorizationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Identity\Definitions\BankingRole;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\BankingAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRoleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class BankingAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_is_exact_idempotent_and_does_not_assign_memberships(): void
    {
        self::assertSame(['BANKING.VIEW', 'BANKING.PAYMENTS_MANAGE', 'BANKING.PAYMENTS_POST'], array_column(BankingPermission::cases(), 'value'));
        self::assertSame(['BANKING.VIEW', 'BANKING.PAYMENTS_MANAGE'], array_column(BankingRole::Manager->permissions(), 'value'));
        self::assertSame(['BANKING.VIEW', 'BANKING.PAYMENTS_POST'], array_column(BankingRole::Poster->permissions(), 'value'));

        $provisioner = $this->app->make(BankingAuthorizationProvisioner::class);
        $provisioner->provision();
        $before = [PermissionRecord::all()->toArray(), RoleRecord::all()->toArray(), RolePermissionRecord::all()->toArray()];
        $provisioner->provision();

        self::assertSame($before, [PermissionRecord::all()->toArray(), RoleRecord::all()->toArray(), RolePermissionRecord::all()->toArray()]);
        self::assertSame(3, PermissionRecord::query()->count());
        self::assertSame(2, RoleRecord::query()->count());
        self::assertSame(4, RolePermissionRecord::query()->where('active', true)->count());
        self::assertSame(0, AdministrationMembershipRoleRecord::query()->count());
    }

    public function test_uuid_or_code_collision_aborts_without_partial_provisioning(): void
    {
        PermissionRecord::query()->create(['id' => BankingPermission::View->id()->toString(), 'code' => 'OTHER.PERMISSION', 'name' => 'Other', 'status' => 'active']);

        $this->expectException(LogicException::class);
        $this->app->make(BankingAuthorizationProvisioner::class)->provision();
    }

    public function test_effective_permissions_are_independent_tenant_scoped_revocable_and_membership_scoped(): void
    {
        $this->app->make(BankingAuthorizationProvisioner::class)->provision();
        $now = now();
        DB::table('domain_users')->insert(['id' => 'b2500000-0000-4000-8000-000000000001', 'display_name' => 'Bank User', 'email' => 'bank@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1, 2] as $n) {
            DB::table('administrations')->insert(['id' => $this->admin($n)->toString(), 'code' => 'BANK'.$n, 'name' => 'Bank '.$n, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('administration_memberships')->insert(['id' => 'b2500000-0000-4000-8000-000000000002', 'user_id' => 'b2500000-0000-4000-8000-000000000001', 'administration_id' => $this->admin(1)->toString(), 'active' => true, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('administration_membership_roles')->insert(['id' => 'b2500000-0000-4000-8000-000000000003', 'membership_id' => 'b2500000-0000-4000-8000-000000000002', 'role_id' => BankingRole::Manager->id()->toString(), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $reader = $this->app->make(AuthorizationReadRepository::class);
        $user = new UserId(new Uuid('b2500000-0000-4000-8000-000000000001'));
        $at = new \DateTimeImmutable('2026-08-26T12:00:00+00:00');
        $ids = array_map(static fn ($id): string => $id->toString(), $reader->effectivePermissionIds($user, $this->admin(1), $at));
        self::assertContains(BankingPermission::View->id()->toString(), $ids);
        self::assertContains(BankingPermission::ManagePayments->id()->toString(), $ids);
        self::assertNotContains(BankingPermission::PostPayments->id()->toString(), $ids);
        self::assertNotContains(SalesPermission::ManageOrders->id()->toString(), $ids);
        self::assertSame([], $reader->effectivePermissionIds($user, $this->admin(2), $at));
        DB::table('administration_membership_roles')->where('id', 'b2500000-0000-4000-8000-000000000003')->update(['active' => false]);
        self::assertSame([], $reader->effectivePermissionIds($user, $this->admin(1), $at));
        DB::table('administration_membership_roles')->where('id', 'b2500000-0000-4000-8000-000000000003')->update(['active' => true]);
        DB::table('administration_memberships')->where('id', 'b2500000-0000-4000-8000-000000000002')->update(['active' => false]);
        self::assertSame([], $reader->effectivePermissionIds($user, $this->admin(1), $at));
    }

    private function admin(int $n): AdministrationId
    {
        return new AdministrationId(new Uuid(sprintf('b2600000-0000-4000-8000-%012d', $n)));
    }
}

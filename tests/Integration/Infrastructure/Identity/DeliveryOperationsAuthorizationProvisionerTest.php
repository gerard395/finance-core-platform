<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Identity;

use App\Application\Identity\AuthorizationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\DeliveryOperationsPermission;
use App\Domain\Identity\Definitions\DeliveryOperationsRole;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Identity\DeliveryOperationsAuthorizationProvisioner;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use Database\Seeders\DeliveryOperationsAuthorizationSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class DeliveryOperationsAuthorizationProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private const USER = 'c1000000-0000-4000-8000-000000000001';

    private const ADMIN_A = 'c2000000-0000-4000-8000-000000000001';

    private const ADMIN_B = 'c2000000-0000-4000-8000-000000000002';

    private const MEMBERSHIP_A = 'c3000000-0000-4000-8000-000000000001';

    private const MEMBERSHIP_B = 'c3000000-0000-4000-8000-000000000002';

    public function test_it_provisions_one_permission_role_and_active_link_idempotently_without_assignment(): void
    {
        $provisioner = $this->app->make(DeliveryOperationsAuthorizationProvisioner::class);
        $provisioner->provision();
        $first = [DB::table('permissions')->count(), DB::table('roles')->count(), DB::table('role_permissions')->count()];

        $this->assertDatabaseHas('permissions', ['id' => DeliveryOperationsPermission::ResolveUnknownOutcome->id()->toString(), 'code' => 'DELIVERY.OUTCOME_RESOLVE', 'name' => 'Resolve Ambiguous Delivery Outcomes', 'status' => 'active']);
        $this->assertDatabaseHas('roles', ['id' => DeliveryOperationsRole::Operator->id()->toString(), 'code' => 'DELIVERY_OPERATOR', 'status' => 'active']);
        $this->assertDatabaseHas('role_permissions', ['id' => '8e510530-4723-46da-932c-dac030c13ac5', 'role_id' => DeliveryOperationsRole::Operator->id()->toString(), 'permission_id' => DeliveryOperationsPermission::ResolveUnknownOutcome->id()->toString(), 'active' => true]);
        self::assertSame(0, DB::table('administration_membership_roles')->count());

        $provisioner->provision();
        $this->seed(DeliveryOperationsAuthorizationSeeder::class);
        self::assertSame($first, [DB::table('permissions')->count(), DB::table('roles')->count(), DB::table('role_permissions')->count()]);
        self::assertSame(1, DB::table('role_permissions')->where('role_id', DeliveryOperationsRole::Operator->id()->toString())->count());
    }

    public function test_collision_is_rejected_transactionally(): void
    {
        DB::table('permissions')->insert(['id' => 'c4000000-0000-4000-8000-000000000001', 'code' => 'DELIVERY.OUTCOME_RESOLVE', 'name' => 'Collision', 'description' => null, 'status' => 'active']);
        try {
            $this->app->make(DeliveryOperationsAuthorizationProvisioner::class)->provision();
            self::fail('Canonical permission collision must fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('ambiguous', $exception->getMessage());
        }
        self::assertSame(1, DB::table('permissions')->count());
        self::assertSame(0, DB::table('roles')->count());
        self::assertSame(0, DB::table('role_permissions')->count());
    }

    public function test_effective_permission_is_tenant_scoped_revocable_and_has_no_privilege_bleed(): void
    {
        $this->app->make(DeliveryOperationsAuthorizationProvisioner::class)->provision();
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        $this->identityFixtures();
        $reader = $this->app->make(AuthorizationReadRepository::class);
        $at = new DateTimeImmutable('2026-08-25T12:00:00+00:00');

        $permissionsA = $reader->effectivePermissionIds($this->user(), $this->admin(self::ADMIN_A), $at);
        self::assertTrue($this->contains($permissionsA, DeliveryOperationsPermission::ResolveUnknownOutcome->id()->toString()));
        self::assertFalse($this->contains($permissionsA, AdministrationPermission::UpdateSettings->id()->toString()));
        self::assertFalse($this->contains($permissionsA, SalesPermission::IssueInvoices->id()->toString()));
        self::assertFalse($this->contains($permissionsA, SalesPermission::ManageQuotations->id()->toString()));
        self::assertSame([], $reader->effectivePermissionIds($this->user(), $this->admin(self::ADMIN_B), $at));

        DB::table('administration_membership_roles')->where('membership_id', self::MEMBERSHIP_A)->update(['active' => false]);
        self::assertSame([], $reader->effectivePermissionIds($this->user(), $this->admin(self::ADMIN_A), $at));
        DB::table('administration_membership_roles')->where('membership_id', self::MEMBERSHIP_A)->update(['active' => true]);
        DB::table('administration_memberships')->where('id', self::MEMBERSHIP_A)->update(['active' => false]);
        self::assertSame([], $reader->effectivePermissionIds($this->user(), $this->admin(self::ADMIN_A), $at));
    }

    private function identityFixtures(): void
    {
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Operator', 'email' => 'operator@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([self::ADMIN_A => 'A', self::ADMIN_B => 'B'] as $id => $code) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'OPS-'.$code, 'name' => 'Operations '.$code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('administration_memberships')->insert([
            ['id' => self::MEMBERSHIP_A, 'user_id' => self::USER, 'administration_id' => self::ADMIN_A, 'active' => true, 'valid_from' => '2026-01-01 00:00:00', 'valid_until' => '2026-12-31 23:59:59', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::MEMBERSHIP_B, 'user_id' => self::USER, 'administration_id' => self::ADMIN_B, 'active' => false, 'valid_from' => '2026-01-01 00:00:00', 'valid_until' => '2026-12-31 23:59:59', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('administration_membership_roles')->insert(['id' => 'c5000000-0000-4000-8000-000000000001', 'membership_id' => self::MEMBERSHIP_A, 'role_id' => DeliveryOperationsRole::Operator->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<PermissionId> $permissions */
    private function contains(array $permissions, string $id): bool
    {
        return in_array($id, array_map(static fn ($permission): string => $permission->toString(), $permissions), true);
    }

    private function user(): UserId
    {
        return new UserId(new Uuid(self::USER));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}

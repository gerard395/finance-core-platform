<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Identity\AdministrationMembershipRepository;
use App\Application\Identity\AuthorizationReadRepository;
use App\Application\Identity\MembershipRoleRepository;
use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAuthorizationReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentAuthorizationReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_multiple_roles_and_distinct_effective_permissions(): void
    {
        $graph = $this->persistAuthorizationGraph();
        $reader = new EloquentAuthorizationReadRepository;

        $roleIds = $reader->activeRoleIdsForMembership($graph['membership']->id());
        $permissionIds = $reader->effectivePermissionIds(
            $graph['user']->id(),
            $graph['administration']->id(),
            new DateTimeImmutable('2026-08-20T12:00:00+00:00'),
        );

        self::assertCount(2, $roleIds);
        self::assertCount(2, $permissionIds);
        self::assertSame(
            [$graph['permissionA']->id()->toString(), $graph['permissionB']->id()->toString()],
            array_map(static fn (PermissionId $id): string => $id->toString(), $permissionIds),
        );
    }

    public function test_inactive_membership_has_no_effective_permissions(): void
    {
        $graph = $this->persistAuthorizationGraph();
        $graph['membership']->deactivate();
        (new EloquentAdministrationMembershipRepository)->save($graph['membership']);

        $permissions = (new EloquentAuthorizationReadRepository)->effectivePermissionIds(
            $graph['user']->id(),
            $graph['administration']->id(),
            new DateTimeImmutable('2026-08-20T12:00:00+00:00'),
        );

        self::assertSame([], $permissions);
    }

    public function test_expired_membership_and_other_administration_have_no_effective_permissions(): void
    {
        $graph = $this->persistAuthorizationGraph();
        $reader = new EloquentAuthorizationReadRepository;

        self::assertSame([], $reader->effectivePermissionIds(
            $graph['user']->id(),
            $graph['administration']->id(),
            new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        ));
        self::assertSame([], $reader->effectivePermissionIds(
            $graph['user']->id(),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440099')),
            new DateTimeImmutable('2026-08-20T12:00:00+00:00'),
        ));
    }

    public function test_inactive_role_permission_and_assignment_are_excluded(): void
    {
        $graph = $this->persistAuthorizationGraph();
        $graph['roleA']->deactivate();
        $graph['rolePermissionB']->deactivate();
        $graph['membershipRoleB']->deactivate();
        (new EloquentRoleRepository)->save($graph['roleA']);
        (new EloquentRolePermissionRepository)->save($graph['rolePermissionB']);
        (new EloquentMembershipRoleRepository)->save($graph['membershipRoleB']);

        $permissions = (new EloquentAuthorizationReadRepository)->effectivePermissionIds(
            $graph['user']->id(),
            $graph['administration']->id(),
            new DateTimeImmutable('2026-08-20T12:00:00+00:00'),
        );

        self::assertSame([], $permissions);
    }

    public function test_membership_role_round_trips_and_duplicate_pair_is_rejected(): void
    {
        $graph = $this->persistAuthorizationGraph();
        $repository = new EloquentMembershipRoleRepository;
        $saved = $repository->findById($graph['membershipRoleA']->id());

        self::assertInstanceOf(MembershipRole::class, $saved);
        self::assertTrue($graph['membership']->id()->equals($saved->membershipId()));
        self::assertTrue($graph['roleA']->id()->equals($saved->roleId()));
        self::assertTrue($saved->isActive());

        $this->expectException(QueryException::class);

        $repository->save(new MembershipRole(
            new MembershipRoleId(new Uuid('550e8400-e29b-41d4-a716-446655440098')),
            $graph['membership']->id(),
            $graph['roleA']->id(),
            true,
        ));
    }

    public function test_all_application_contracts_resolve_to_infrastructure_adapters(): void
    {
        self::assertInstanceOf(EloquentAdministrationMembershipRepository::class, $this->app->make(AdministrationMembershipRepository::class));
        self::assertInstanceOf(EloquentRoleRepository::class, $this->app->make(RoleRepository::class));
        self::assertInstanceOf(EloquentPermissionRepository::class, $this->app->make(PermissionRepository::class));
        self::assertInstanceOf(EloquentRolePermissionRepository::class, $this->app->make(RolePermissionRepository::class));
        self::assertInstanceOf(EloquentMembershipRoleRepository::class, $this->app->make(MembershipRoleRepository::class));
        self::assertInstanceOf(EloquentAuthorizationReadRepository::class, $this->app->make(AuthorizationReadRepository::class));
    }

    /** @return array<string, mixed> */
    private function persistAuthorizationGraph(): array
    {
        $user = new User(
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new DisplayName('Authorization User'),
            new EmailAddress('authorization@example.com'),
            UserStatus::Active,
        );
        $administration = new Administration(
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new AdministrationCode('AUTHZ'),
            new AdministrationName('Authorization Administration'),
            null,
            new Currency('EUR'),
            AdministrationStatus::Active,
        );
        $membership = new AdministrationMembership(
            new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-446655440003')),
            $user->id(),
            $administration->id(),
            true,
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
        );
        $roleA = $this->role('10', 'finance_admin', 'Finance administrator');
        $roleB = $this->role('11', 'auditor', 'Auditor');
        $permissionA = $this->permission('20', 'reporting.view', 'View reporting');
        $permissionB = $this->permission('21', 'reporting.export', 'Export reporting');
        $membershipRoleA = $this->membershipRole('30', $membership->id(), $roleA->id());
        $membershipRoleB = $this->membershipRole('31', $membership->id(), $roleB->id());
        $rolePermissionA = $this->rolePermission('40', $roleA->id(), $permissionA->id());
        $rolePermissionB = $this->rolePermission('41', $roleA->id(), $permissionB->id());
        $rolePermissionC = $this->rolePermission('42', $roleB->id(), $permissionB->id());

        (new EloquentUserRepository)->save($user);
        (new EloquentAdministrationRepository)->save($administration);
        (new EloquentAdministrationMembershipRepository)->save($membership);
        $roles = new EloquentRoleRepository;
        $roles->save($roleA);
        $roles->save($roleB);
        $permissions = new EloquentPermissionRepository;
        $permissions->save($permissionA);
        $permissions->save($permissionB);
        $membershipRoles = new EloquentMembershipRoleRepository;
        $membershipRoles->save($membershipRoleA);
        $membershipRoles->save($membershipRoleB);
        $rolePermissions = new EloquentRolePermissionRepository;
        $rolePermissions->save($rolePermissionA);
        $rolePermissions->save($rolePermissionB);
        $rolePermissions->save($rolePermissionC);

        return compact(
            'user',
            'administration',
            'membership',
            'roleA',
            'roleB',
            'permissionA',
            'permissionB',
            'membershipRoleA',
            'membershipRoleB',
            'rolePermissionA',
            'rolePermissionB',
            'rolePermissionC',
        );
    }

    private function role(string $suffix, string $code, string $name): Role
    {
        return new Role(
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)),
            new RoleCode($code),
            new RoleName($name),
            null,
            RoleStatus::Active,
        );
    }

    private function permission(string $suffix, string $code, string $name): Permission
    {
        return new Permission(
            new PermissionId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)),
            new PermissionCode($code),
            new PermissionName($name),
            null,
            PermissionStatus::Active,
        );
    }

    private function membershipRole(
        string $suffix,
        AdministrationMembershipId $membershipId,
        RoleId $roleId,
    ): MembershipRole {
        return new MembershipRole(
            new MembershipRoleId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)),
            $membershipId,
            $roleId,
            true,
        );
    }

    private function rolePermission(string $suffix, RoleId $roleId, PermissionId $permissionId): RolePermission
    {
        return new RolePermission(
            new RolePermissionId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)),
            $roleId,
            $permissionId,
            true,
        );
    }
}

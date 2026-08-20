<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\AuthorizationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentAuthorizationReadRepository implements AuthorizationReadRepository
{
    public function activeRoleIdsForMembership(AdministrationMembershipId $membershipId): array
    {
        return DB::table('administration_membership_roles')
            ->join('roles', 'roles.id', '=', 'administration_membership_roles.role_id')
            ->where('administration_membership_roles.membership_id', $membershipId->toString())
            ->where('administration_membership_roles.active', true)
            ->where('roles.status', RoleStatus::Active->value)
            ->orderBy('roles.id')
            ->pluck('roles.id')
            ->map(fn (string $id): RoleId => new RoleId(new Uuid($id)))
            ->all();
    }

    public function effectivePermissionIds(
        UserId $userId,
        AdministrationId $administrationId,
        DateTimeImmutable $at,
    ): array {
        $utc = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return DB::table('administration_memberships')
            ->join(
                'administration_membership_roles',
                'administration_membership_roles.membership_id',
                '=',
                'administration_memberships.id',
            )
            ->join('roles', 'roles.id', '=', 'administration_membership_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('administration_memberships.user_id', $userId->toString())
            ->where('administration_memberships.administration_id', $administrationId->toString())
            ->where('administration_memberships.active', true)
            ->where('administration_memberships.valid_from', '<=', $utc)
            ->where('administration_memberships.valid_until', '>=', $utc)
            ->where('administration_membership_roles.active', true)
            ->where('roles.status', RoleStatus::Active->value)
            ->where('role_permissions.active', true)
            ->where('permissions.status', PermissionStatus::Active->value)
            ->distinct()
            ->orderBy('permissions.id')
            ->pluck('permissions.id')
            ->map(fn (string $id): PermissionId => new PermissionId(new Uuid($id)))
            ->all();
    }
}

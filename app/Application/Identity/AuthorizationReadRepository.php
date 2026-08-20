<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

interface AuthorizationReadRepository
{
    /** @return list<RoleId> */
    public function activeRoleIdsForMembership(AdministrationMembershipId $membershipId): array;

    /** @return list<PermissionId> */
    public function effectivePermissionIds(
        UserId $userId,
        AdministrationId $administrationId,
        DateTimeImmutable $at,
    ): array;
}

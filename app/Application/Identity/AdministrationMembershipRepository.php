<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\UserId;

interface AdministrationMembershipRepository
{
    public function findByUserAndAdministration(
        UserId $userId,
        AdministrationId $administrationId,
    ): ?AdministrationMembership;

    /** @return list<AdministrationMembership> */
    public function findForUser(UserId $userId): array;

    public function save(AdministrationMembership $membership): void;
}

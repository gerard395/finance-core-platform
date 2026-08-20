<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\MembershipRoleId;

interface MembershipRoleRepository
{
    public function findById(MembershipRoleId $id): ?MembershipRole;

    public function save(MembershipRole $assignment): void;
}

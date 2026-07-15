<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\RoleId;

final class MembershipRole
{
    public function __construct(
        private readonly MembershipRoleId $id,
        private readonly AdministrationMembershipId $membershipId,
        private readonly RoleId $roleId,
        private bool $active,
    ) {}

    public function id(): MembershipRoleId
    {
        return $this->id;
    }

    public function membershipId(): AdministrationMembershipId
    {
        return $this->membershipId;
    }

    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}

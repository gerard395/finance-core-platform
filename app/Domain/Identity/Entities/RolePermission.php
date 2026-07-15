<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RolePermissionId;

final class RolePermission
{
    public function __construct(
        private readonly RolePermissionId $id,
        private readonly RoleId $roleId,
        private readonly PermissionId $permissionId,
        private bool $active,
    ) {}

    public function id(): RolePermissionId
    {
        return $this->id;
    }

    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    public function permissionId(): PermissionId
    {
        return $this->permissionId;
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

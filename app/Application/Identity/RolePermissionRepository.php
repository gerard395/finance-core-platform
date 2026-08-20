<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\ValueObjects\RolePermissionId;

interface RolePermissionRepository
{
    public function findById(RolePermissionId $id): ?RolePermission;

    public function save(RolePermission $assignment): void;
}

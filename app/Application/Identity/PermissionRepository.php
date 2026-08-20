<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\ValueObjects\PermissionId;

interface PermissionRepository
{
    public function findById(PermissionId $id): ?Permission;

    public function save(Permission $permission): void;
}

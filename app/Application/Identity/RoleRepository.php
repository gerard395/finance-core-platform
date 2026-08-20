<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\ValueObjects\RoleId;

interface RoleRepository
{
    public function findById(RoleId $id): ?Role;

    public function save(Role $role): void;
}

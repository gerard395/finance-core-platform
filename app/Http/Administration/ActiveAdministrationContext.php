<?php

declare(strict_types=1);

namespace App\Http\Administration;

use App\Domain\Administration\Entities\Administration;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\ValueObjects\PermissionId;

final readonly class ActiveAdministrationContext
{
    /** @param list<PermissionId> $permissionIds */
    public function __construct(
        public User $user,
        public Administration $administration,
        public array $permissionIds,
    ) {}
}

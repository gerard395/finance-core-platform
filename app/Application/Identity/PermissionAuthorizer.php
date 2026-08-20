<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\ValueObjects\PermissionId;

final readonly class PermissionAuthorizer
{
    /** @param list<PermissionId> $effectivePermissionIds */
    public function allows(array $effectivePermissionIds, PermissionId $requiredPermissionId): bool
    {
        foreach ($effectivePermissionIds as $effectivePermissionId) {
            if ($effectivePermissionId->equals($requiredPermissionId)) {
                return true;
            }
        }

        return false;
    }
}

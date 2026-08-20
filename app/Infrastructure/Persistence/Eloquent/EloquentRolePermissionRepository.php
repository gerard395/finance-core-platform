<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\RolePermissionRepository;
use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;

final class EloquentRolePermissionRepository implements RolePermissionRepository
{
    public function findById(RolePermissionId $id): ?RolePermission
    {
        $record = RolePermissionRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new RolePermission(
            new RolePermissionId(new Uuid($record->getAttribute('id'))),
            new RoleId(new Uuid($record->getAttribute('role_id'))),
            new PermissionId(new Uuid($record->getAttribute('permission_id'))),
            $record->getAttribute('active'),
        );
    }

    public function save(RolePermission $assignment): void
    {
        RolePermissionRecord::query()->updateOrCreate(
            ['id' => $assignment->id()->toString()],
            [
                'role_id' => $assignment->roleId()->toString(),
                'permission_id' => $assignment->permissionId()->toString(),
                'active' => $assignment->isActive(),
            ],
        );
    }
}

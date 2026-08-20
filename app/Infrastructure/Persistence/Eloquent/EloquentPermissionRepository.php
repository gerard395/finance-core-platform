<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\PermissionRepository;
use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;

final class EloquentPermissionRepository implements PermissionRepository
{
    public function findById(PermissionId $id): ?Permission
    {
        $record = PermissionRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new Permission(
            new PermissionId(new Uuid($record->getAttribute('id'))),
            new PermissionCode($record->getAttribute('code')),
            new PermissionName($record->getAttribute('name')),
            $record->getAttribute('description'),
            PermissionStatus::from($record->getAttribute('status')),
        );
    }

    public function save(Permission $permission): void
    {
        PermissionRecord::query()->updateOrCreate(
            ['id' => $permission->id()->toString()],
            [
                'code' => $permission->code()->toString(),
                'name' => $permission->name()->toString(),
                'description' => $permission->description(),
                'status' => $permission->status()->value,
            ],
        );
    }
}

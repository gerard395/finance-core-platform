<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\RoleRepository;
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;

final class EloquentRoleRepository implements RoleRepository
{
    public function findById(RoleId $id): ?Role
    {
        $record = RoleRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new Role(
            new RoleId(new Uuid($record->getAttribute('id'))),
            new RoleCode($record->getAttribute('code')),
            new RoleName($record->getAttribute('name')),
            $record->getAttribute('description'),
            RoleStatus::from($record->getAttribute('status')),
        );
    }

    public function save(Role $role): void
    {
        RoleRecord::query()->updateOrCreate(
            ['id' => $role->id()->toString()],
            [
                'code' => $role->code()->toString(),
                'name' => $role->name()->toString(),
                'description' => $role->description(),
                'status' => $role->status()->value,
            ],
        );
    }
}

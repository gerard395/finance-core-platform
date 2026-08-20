<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\MembershipRoleRepository;
use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRoleRecord;

final class EloquentMembershipRoleRepository implements MembershipRoleRepository
{
    public function findById(MembershipRoleId $id): ?MembershipRole
    {
        $record = AdministrationMembershipRoleRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new MembershipRole(
            new MembershipRoleId(new Uuid($record->getAttribute('id'))),
            new AdministrationMembershipId(new Uuid($record->getAttribute('membership_id'))),
            new RoleId(new Uuid($record->getAttribute('role_id'))),
            $record->getAttribute('active'),
        );
    }

    public function save(MembershipRole $assignment): void
    {
        AdministrationMembershipRoleRecord::query()->updateOrCreate(
            ['id' => $assignment->id()->toString()],
            [
                'membership_id' => $assignment->membershipId()->toString(),
                'role_id' => $assignment->roleId()->toString(),
                'active' => $assignment->isActive(),
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\UserRepository;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\DomainUserRecord;

final class EloquentUserRepository implements UserRepository
{
    public function findById(UserId $id): ?User
    {
        $record = DomainUserRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new User(
            new UserId(new Uuid($record->getAttribute('id'))),
            new DisplayName($record->getAttribute('display_name')),
            new EmailAddress($record->getAttribute('email')),
            UserStatus::from($record->getAttribute('status')),
        );
    }

    public function save(User $user): void
    {
        DomainUserRecord::query()->updateOrCreate(
            ['id' => $user->id()->toString()],
            [
                'display_name' => $user->displayName()->toString(),
                'email' => $user->emailAddress()->toString(),
                'status' => $user->status()->value,
            ],
        );
    }
}

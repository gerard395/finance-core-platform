<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Identity\AuthAccount;
use App\Application\Identity\AuthAccountStore;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Models\User as AuthUser;

final class EloquentAuthAccountStore implements AuthAccountStore
{
    public function existsByEmail(EmailAddress $emailAddress): bool
    {
        return AuthUser::query()->where('email', $emailAddress->toString())->exists();
    }

    public function create(
        UserId $domainUserId,
        DisplayName $displayName,
        EmailAddress $emailAddress,
        string $passwordHash,
    ): AuthAccount {
        $record = new AuthUser;
        $record->setAttribute('domain_user_id', $domainUserId->toString());
        $record->setAttribute('name', $displayName->toString());
        $record->setAttribute('email', $emailAddress->toString());
        $record->setAttribute('password', $passwordHash);
        $record->save();

        return $this->toAuthAccount($record);
    }

    public function findByDomainUserId(UserId $domainUserId): ?AuthAccount
    {
        $record = AuthUser::query()
            ->where('domain_user_id', $domainUserId->toString())
            ->first();

        return $record === null ? null : $this->toAuthAccount($record);
    }

    private function toAuthAccount(AuthUser $record): AuthAccount
    {
        return new AuthAccount(
            (int) $record->getKey(),
            new UserId(new Uuid($record->getAttribute('domain_user_id'))),
            $record->getAttribute('email'),
        );
    }
}

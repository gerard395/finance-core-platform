<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;

interface AuthAccountStore
{
    public function existsByEmail(EmailAddress $emailAddress): bool;

    public function create(
        UserId $domainUserId,
        DisplayName $displayName,
        EmailAddress $emailAddress,
        string $passwordHash,
    ): AuthAccount;

    public function findByDomainUserId(UserId $domainUserId): ?AuthAccount;
}

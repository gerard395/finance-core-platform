<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\ValueObjects\UserId;

final readonly class ProvisionUserAccountResult
{
    public function __construct(
        private UserId $domainUserId,
        private int $authAccountId,
    ) {}

    public function domainUserId(): UserId
    {
        return $this->domainUserId;
    }

    public function authAccountId(): int
    {
        return $this->authAccountId;
    }
}

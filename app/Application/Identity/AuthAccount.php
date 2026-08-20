<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\ValueObjects\UserId;

final readonly class AuthAccount
{
    public function __construct(
        private int $id,
        private UserId $domainUserId,
        private string $email,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function domainUserId(): UserId
    {
        return $this->domainUserId;
    }

    public function email(): string
    {
        return $this->email;
    }
}

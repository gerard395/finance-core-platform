<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private DisplayName $displayName,
        private EmailAddress $emailAddress,
        private UserStatus $status,
    ) {}

    public function id(): UserId
    {
        return $this->id;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }

    public function emailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function rename(DisplayName $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function changeEmailAddress(EmailAddress $emailAddress): void
    {
        $this->emailAddress = $emailAddress;
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
    }
}

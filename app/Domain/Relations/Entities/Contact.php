<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;

final class Contact
{
    public function __construct(
        private readonly ContactId $id,
        private ContactName $name,
        private ?EmailAddress $emailAddress,
        private ?PhoneNumber $phoneNumber,
        private ContactStatus $status,
    ) {}

    public function id(): ContactId
    {
        return $this->id;
    }

    public function name(): ContactName
    {
        return $this->name;
    }

    public function emailAddress(): ?EmailAddress
    {
        return $this->emailAddress;
    }

    public function phoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function status(): ContactStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === ContactStatus::Active;
    }

    public function rename(ContactName $name): void
    {
        $this->name = $name;
    }

    public function changeEmailAddress(?EmailAddress $emailAddress): void
    {
        $this->emailAddress = $emailAddress;
    }

    public function changePhoneNumber(?PhoneNumber $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function activate(): void
    {
        $this->status = ContactStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = ContactStatus::Inactive;
    }
}

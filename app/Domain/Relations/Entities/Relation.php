<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use DomainException;

final class Relation
{
    /** @var array<string, Contact> */
    private array $contacts = [];

    public function __construct(
        private readonly RelationId $id,
        private readonly RelationCode $code,
        private DisplayName $displayName,
        private bool $active,
    ) {}

    public function id(): RelationId
    {
        return $this->id;
    }

    public function code(): RelationCode
    {
        return $this->code;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return list<Contact> */
    public function contacts(): array
    {
        return array_values($this->contacts);
    }

    public function hasContact(ContactId $contactId): bool
    {
        return isset($this->contacts[$contactId->toString()]);
    }

    public function contact(ContactId $contactId): ?Contact
    {
        return $this->contacts[$contactId->toString()] ?? null;
    }

    public function addContact(Contact $contact): void
    {
        $key = $contact->id()->toString();

        if (isset($this->contacts[$key])) {
            throw new DomainException('Relation already contains a Contact with this identity.');
        }

        $this->contacts[$key] = $contact;
    }

    public function removeContact(ContactId $contactId): void
    {
        unset($this->contacts[$contactId->toString()]);
    }

    public function rename(DisplayName $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}

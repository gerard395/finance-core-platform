<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\ContactWriter;
use App\Application\Relations\ContactWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Infrastructure\Persistence\Eloquent\Models\RelationContactRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentContactWriter implements ContactWriter
{
    public function create(AdministrationId $administrationId, Relation $relation, ContactId $contactId): ContactWriteResult
    {
        $contact = $this->validatedContact($administrationId, $relation, $contactId);
        if ($contact === null) {
            return ContactWriteResult::NotFound;
        }

        try {
            RelationContactRecord::query()->create($this->attributes($administrationId, $relation, $contact));
        } catch (QueryException $exception) {
            if (RelationContactRecord::query()->whereKey($contactId->toString())->exists()) {
                return ContactWriteResult::DuplicateIdentity;
            }
            throw $exception;
        }

        return ContactWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, Relation $relation, ContactId $contactId): ContactWriteResult
    {
        $contact = $this->validatedContact($administrationId, $relation, $contactId);
        if ($contact === null) {
            return ContactWriteResult::NotFound;
        }

        $record = RelationContactRecord::query()->whereKey($contactId->toString())
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relation->id()->toString())->first();
        if ($record === null) {
            return ContactWriteResult::NotFound;
        }
        $record->fill($this->attributes($administrationId, $relation, $contact));
        $record->save();

        return ContactWriteResult::Success;
    }

    private function validatedContact(AdministrationId $administrationId, Relation $relation, ContactId $contactId): ?Contact
    {
        if ($relation->bankAccounts() !== []) {
            throw new DomainException('Unsupported Relation child state cannot be discarded by Contact persistence.');
        }
        if (! RelationRecord::query()->whereKey($relation->id()->toString())->where('administration_id', $administrationId->toString())->exists()) {
            return null;
        }

        return $relation->contact($contactId);
    }

    /** @return array<string, string|null> */
    private function attributes(AdministrationId $administrationId, Relation $relation, Contact $contact): array
    {
        return [
            'contact_id' => $contact->id()->toString(),
            'administration_id' => $administrationId->toString(),
            'relation_id' => $relation->id()->toString(),
            'contact_name' => $contact->name()->toString(),
            'email' => $contact->emailAddress()?->toString(),
            'phone' => $contact->phoneNumber()?->toString(),
            'status' => $contact->status()->value,
        ];
    }
}

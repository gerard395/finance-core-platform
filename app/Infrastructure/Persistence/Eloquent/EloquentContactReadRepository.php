<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\ContactDetail;
use App\Application\Relations\ContactListItem;
use App\Application\Relations\ContactReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationContactRecord;

final class EloquentContactReadRepository implements ContactReadRepository
{
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array
    {
        return RelationContactRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->orderBy('contact_name')->orderBy('contact_id')->get()
            ->map(fn (RelationContactRecord $record): ContactListItem => new ContactListItem(
                new ContactId(new Uuid($record->getAttribute('contact_id'))),
                new ContactName($record->getAttribute('contact_name')),
                ContactStatus::from($record->getAttribute('status')),
            ))->all();
    }

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, ContactId $contactId): ?ContactDetail
    {
        $record = RelationContactRecord::query()
            ->whereKey($contactId->toString())
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())->first();

        if ($record === null) {
            return null;
        }

        $email = $record->getAttribute('email');
        $phone = $record->getAttribute('phone');

        return new ContactDetail(
            new ContactId(new Uuid($record->getAttribute('contact_id'))),
            new ContactName($record->getAttribute('contact_name')),
            $email === null ? null : new EmailAddress($email),
            $phone === null ? null : new PhoneNumber($phone),
            ContactStatus::from($record->getAttribute('status')),
        );
    }
}

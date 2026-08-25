<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesDocumentRecipient;
use App\Application\Sales\SalesDocumentRecipientPreferenceStore;
use App\Application\Sales\SalesDocumentRecipientPurpose;
use App\Application\Sales\SalesDocumentRecipientReader;
use App\Application\Sales\SalesDocumentRecipientStatus;
use App\Application\Sales\SetPreferredSalesDocumentRecipientResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesDocumentRecipientPreferenceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationContactRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesDocumentRecipientPreferenceRecord;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentSalesDocumentRecipientPreferences implements SalesDocumentRecipientPreferenceStore, SalesDocumentRecipientReader
{
    public function set(SalesDocumentRecipientPreferenceId $id, AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose, ContactId $contactId): SetPreferredSalesDocumentRecipientResult
    {
        return DB::transaction(function () use ($id, $administrationId, $relationId, $purpose, $contactId): SetPreferredSalesDocumentRecipientResult {
            $relationExists = RelationRecord::query()
                ->whereKey($relationId->toString())
                ->where('administration_id', $administrationId->toString())
                ->lockForUpdate()
                ->exists();
            if (! $relationExists) {
                return SetPreferredSalesDocumentRecipientResult::InvalidContact;
            }
            $contact = RelationContactRecord::query()
                ->whereKey($contactId->toString())
                ->where('administration_id', $administrationId->toString())
                ->where('relation_id', $relationId->toString())
                ->first();
            if ($contact === null || $contact->getAttribute('status') !== 'active' || $contact->getAttribute('email') === null) {
                return SetPreferredSalesDocumentRecipientResult::InvalidContact;
            }
            try {
                new EmailAddress($contact->getAttribute('email'));
            } catch (InvalidArgumentException) {
                return SetPreferredSalesDocumentRecipientResult::InvalidContact;
            }

            $preference = SalesDocumentRecipientPreferenceRecord::query()
                ->where('administration_id', $administrationId->toString())
                ->where('relation_id', $relationId->toString())
                ->where('purpose', $purpose->value)
                ->first();
            if ($preference === null) {
                SalesDocumentRecipientPreferenceRecord::query()->create([
                    'id' => $id->toString(), 'administration_id' => $administrationId->toString(),
                    'relation_id' => $relationId->toString(), 'purpose' => $purpose->value,
                    'contact_id' => $contactId->toString(),
                ]);
            } else {
                $preference->fill(['contact_id' => $contactId->toString()])->save();
            }

            return SetPreferredSalesDocumentRecipientResult::Success;
        });
    }

    public function clear(AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose): bool
    {
        SalesDocumentRecipientPreferenceRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->where('purpose', $purpose->value)
            ->delete();

        return true;
    }

    public function read(AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose): SalesDocumentRecipient
    {
        $preference = SalesDocumentRecipientPreferenceRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->where('purpose', $purpose->value)
            ->first();
        if ($preference === null) {
            return new SalesDocumentRecipient(SalesDocumentRecipientStatus::Missing);
        }
        $contact = RelationContactRecord::query()
            ->whereKey($preference->getAttribute('contact_id'))
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->first();
        if ($contact === null || $contact->getAttribute('status') !== 'active' || $contact->getAttribute('email') === null) {
            return new SalesDocumentRecipient(SalesDocumentRecipientStatus::Invalid);
        }

        try {
            return new SalesDocumentRecipient(
                SalesDocumentRecipientStatus::Success,
                new ContactId(new Uuid($contact->getAttribute('contact_id'))),
                new ContactName($contact->getAttribute('contact_name')),
                new EmailAddress($contact->getAttribute('email')),
            );
        } catch (InvalidArgumentException) {
            return new SalesDocumentRecipient(SalesDocumentRecipientStatus::Invalid);
        }
    }
}

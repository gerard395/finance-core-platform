<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class UpdateContact
{
    public function __construct(private RelationReadRepository $relations, private ContactWriter $contacts, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, ContactId $contactId, ContactName $name, ?EmailAddress $emailAddress, ?PhoneNumber $phoneNumber): ContactWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $contactId, $name, $emailAddress, $phoneNumber): ContactWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            $contact = $relation?->contact($contactId);

            if ($relation === null || $contact === null) {
                return ContactWriteResult::NotFound;
            }

            $contact->rename($name);
            $contact->changeEmailAddress($emailAddress);
            $contact->changePhoneNumber($phoneNumber);

            return $this->contacts->update($administrationId, $relation, $contactId);
        });
    }
}

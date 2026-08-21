<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class CreateContact
{
    public function __construct(
        private RelationReadRepository $relations,
        private ContactWriter $contacts,
        private TransactionManager $transactions,
    ) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, ContactId $contactId, ContactName $name, ?EmailAddress $emailAddress, ?PhoneNumber $phoneNumber): ContactWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $contactId, $name, $emailAddress, $phoneNumber): ContactWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);

            if ($relation === null) {
                return ContactWriteResult::NotFound;
            }

            if ($relation->hasContact($contactId)) {
                return ContactWriteResult::DuplicateIdentity;
            }

            $relation->addContact(new Contact($contactId, $name, $emailAddress, $phoneNumber, ContactStatus::Active));

            return $this->contacts->create($administrationId, $relation, $contactId);
        });
    }
}

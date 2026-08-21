<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class ActivateContact
{
    public function __construct(private RelationReadRepository $relations, private ContactWriter $contacts, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, ContactId $contactId): ContactWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $contactId): ContactWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            $contact = $relation?->contact($contactId);
            if ($relation === null || $contact === null) {
                return ContactWriteResult::NotFound;
            }
            $contact->activate();

            return $this->contacts->update($administrationId, $relation, $contactId);
        });
    }
}

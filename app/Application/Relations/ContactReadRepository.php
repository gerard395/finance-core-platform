<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\RelationId;

interface ContactReadRepository
{
    /** @return list<ContactListItem> */
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array;

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, ContactId $contactId): ?ContactDetail;
}

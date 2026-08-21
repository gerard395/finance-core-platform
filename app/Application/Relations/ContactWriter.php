<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\ContactId;

interface ContactWriter
{
    public function create(AdministrationId $administrationId, Relation $relation, ContactId $contactId): ContactWriteResult;

    public function update(AdministrationId $administrationId, Relation $relation, ContactId $contactId): ContactWriteResult;
}

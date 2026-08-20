<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\RelationId;

interface RelationReadRepository
{
    public function findByIdForAdministration(AdministrationId $administrationId, RelationId $relationId): ?Relation;

    /** @return list<Relation> */
    public function findForAdministration(AdministrationId $administrationId): array;
}

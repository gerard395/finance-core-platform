<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\ValueObjects\RelationId;

interface CustomerReadRepository
{
    /** @return list<Customer> */
    public function findForAdministration(AdministrationId $administrationId): array;

    public function existsForRelation(AdministrationId $administrationId, RelationId $relationId): bool;

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId): ?Customer;
}

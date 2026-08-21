<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\RelationId;

interface AddressReadRepository
{
    /** @return list<AddressListItem> */
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array;

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): ?AddressDetail;
}

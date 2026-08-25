<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\RelationId;

interface QuotationAddressResolver
{
    public function resolve(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): QuotationAddressResolution;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;

interface SalesInvoiceAddressResolver
{
    public function resolve(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): ?SalesAddressSnapshot;
}

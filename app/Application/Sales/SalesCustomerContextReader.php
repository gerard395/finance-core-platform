<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;

interface SalesCustomerContextReader
{
    public function read(
        AdministrationId $administrationId,
        CustomerId $customerId,
        ?AddressId $invoiceAddressId,
    ): SalesCustomerContext;
}

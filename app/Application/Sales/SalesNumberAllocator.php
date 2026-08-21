<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface SalesNumberAllocator
{
    public function next(AdministrationId $administrationId, SalesNumberType $type): SalesNumberAllocationResult;
}

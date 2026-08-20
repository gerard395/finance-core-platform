<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface RelationNumberAllocator
{
    public function next(AdministrationId $administrationId, RelationNumberType $type): RelationNumberAllocationResult;
}

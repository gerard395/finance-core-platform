<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\SupplierId;

interface RelationClassificationIdentityGenerator
{
    public function customerId(): CustomerId;

    public function supplierId(): SupplierId;
}

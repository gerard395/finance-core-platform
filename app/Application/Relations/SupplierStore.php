<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Supplier;

interface SupplierStore
{
    public function save(AdministrationId $administrationId, Supplier $supplier): void;
}

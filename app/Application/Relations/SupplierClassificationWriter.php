<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Supplier;

interface SupplierClassificationWriter
{
    /** @throws ClassificationPersistenceConflict */
    public function persist(AdministrationId $administrationId, Supplier $supplier): void;
}

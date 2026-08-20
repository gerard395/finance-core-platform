<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;

interface CustomerClassificationWriter
{
    /** @throws ClassificationPersistenceConflict */
    public function persist(AdministrationId $administrationId, Customer $customer): void;
}

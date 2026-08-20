<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;

interface CustomerStore
{
    public function save(AdministrationId $administrationId, Customer $customer): void;
}

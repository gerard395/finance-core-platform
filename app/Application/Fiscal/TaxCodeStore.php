<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxCode;

interface TaxCodeStore
{
    public function save(AdministrationId $administrationId, TaxCode $taxCode): void;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface EligibleSalesCreditSourceReadRepository
{
    public function listEligible(EligibleSalesCreditSourceQuery $query): PaginatedEligibleSalesCreditSources;
}

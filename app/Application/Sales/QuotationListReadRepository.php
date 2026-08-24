<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface QuotationListReadRepository
{
    public function search(QuotationListQuery $query): PaginatedQuotationList;
}

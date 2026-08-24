<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesCreditInvoiceListReadRepository
{
    public function search(SalesCreditInvoiceListQuery $query): PaginatedSalesCreditInvoiceList;
}

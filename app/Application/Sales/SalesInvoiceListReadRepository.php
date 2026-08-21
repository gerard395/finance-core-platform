<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesInvoiceListReadRepository
{
    public function search(SalesInvoiceListQuery $query): PaginatedSalesInvoiceList;
}

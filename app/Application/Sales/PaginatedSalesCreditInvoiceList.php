<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class PaginatedSalesCreditInvoiceList
{
    /** @param list<SalesCreditInvoiceListItem> $items */
    public function __construct(public array $items, public int $page, public int $perPage, public int $total) {}
}

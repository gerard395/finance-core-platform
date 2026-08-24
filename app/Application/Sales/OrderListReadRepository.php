<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface OrderListReadRepository
{
    public function search(OrderListQuery $query): PaginatedOrderList;
}

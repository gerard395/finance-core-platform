<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class PaginatedOrderList
{
    /** @param list<OrderListItem> $items */
    public function __construct(private array $items, private int $page, private int $perPage, private int $total) {}

    /** @return list<OrderListItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->total;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class PaginatedEligibleSalesCreditSources
{
    /** @param list<EligibleSalesCreditSource> $items */
    public function __construct(private array $items, private int $page, private int $perPage, private int $total) {}

    /** @return list<EligibleSalesCreditSource> */
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

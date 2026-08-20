<?php

declare(strict_types=1);

namespace App\Application\Relations;

use InvalidArgumentException;

final readonly class PaginatedRelationList
{
    /** @param list<RelationListItem> $items */
    public function __construct(private array $items, private int $page, private int $perPage, private int $total)
    {
        if ($page < 1 || $perPage < 1 || $total < 0 || count($items) > $perPage) {
            throw new InvalidArgumentException('Invalid paginated Relation list state.');
        }
    }

    /** @return list<RelationListItem> */
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

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->lastPage();
    }
}

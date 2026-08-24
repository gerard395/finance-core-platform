<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use InvalidArgumentException;

final readonly class EligibleSalesCreditSourceQuery
{
    private ?string $search;

    public function __construct(private AdministrationId $administrationId, ?string $search = null, private EligibleSalesCreditSourceSortField $sortField = EligibleSalesCreditSourceSortField::InvoiceDate, private SalesInvoiceSortDirection $sortDirection = SalesInvoiceSortDirection::Descending, private int $page = 1, private int $perPage = 25)
    {
        if ($page < 1 || ! in_array($perPage, [1, 25, 50, 100], true)) {
            throw new InvalidArgumentException('Invalid eligible SalesCreditInvoice source query.');
        }
        $normalized = $search === null ? null : trim($search);
        $this->search = $normalized === '' ? null : $normalized;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function search(): ?string
    {
        return $this->search;
    }

    public function sortField(): EligibleSalesCreditSourceSortField
    {
        return $this->sortField;
    }

    public function sortDirection(): SalesInvoiceSortDirection
    {
        return $this->sortDirection;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SalesInvoiceListQuery
{
    private ?string $search;

    public function __construct(private AdministrationId $administrationId, ?string $search = null, private ?SalesInvoiceStatus $status = null, private ?CustomerId $customerId = null, private ?DateTimeImmutable $dateFrom = null, private ?DateTimeImmutable $dateTo = null, private SalesInvoiceSortField $sortField = SalesInvoiceSortField::InvoiceDate, private SalesInvoiceSortDirection $sortDirection = SalesInvoiceSortDirection::Descending, private int $page = 1, private int $perPage = 25)
    {
        if ($page < 1 || ! in_array($perPage, [25, 50, 100], true) || ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom)) {
            throw new InvalidArgumentException('Invalid SalesInvoice list query.');
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

    public function status(): ?SalesInvoiceStatus
    {
        return $this->status;
    }

    public function customerId(): ?CustomerId
    {
        return $this->customerId;
    }

    public function dateFrom(): ?DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function dateTo(): ?DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function sortField(): SalesInvoiceSortField
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

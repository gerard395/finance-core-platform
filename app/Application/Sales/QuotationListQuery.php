<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\QuotationStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class QuotationListQuery
{
    private ?string $search;

    public function __construct(
        private AdministrationId $administrationId,
        ?string $search = null,
        private ?QuotationStatus $status = null,
        private ?CustomerId $customerId = null,
        private ?DateTimeImmutable $dateFrom = null,
        private ?DateTimeImmutable $dateTo = null,
        private QuotationSortField $sortField = QuotationSortField::QuotationDate,
        private QuotationSortDirection $sortDirection = QuotationSortDirection::Descending,
        private int $page = 1,
        private int $perPage = 25,
    ) {
        if ($page < 1 || ! in_array($perPage, [25, 50, 100], true) || ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom)) {
            throw new InvalidArgumentException('Invalid Quotation list query.');
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

    public function status(): ?QuotationStatus
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

    public function sortField(): QuotationSortField
    {
        return $this->sortField;
    }

    public function sortDirection(): QuotationSortDirection
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

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SalesCreditInvoiceListQuery
{
    public ?string $search;

    public function __construct(public AdministrationId $administrationId, ?string $search = null, public ?SalesCreditInvoiceStatus $status = null, public ?CustomerId $customerId = null, public ?DateTimeImmutable $dateFrom = null, public ?DateTimeImmutable $dateTo = null, public SalesCreditInvoiceSortField $sortField = SalesCreditInvoiceSortField::CreditDate, public SalesInvoiceSortDirection $sortDirection = SalesInvoiceSortDirection::Descending, public int $page = 1, public int $perPage = 25)
    {
        if ($page < 1 || ! in_array($perPage, [25, 50, 100], true) || ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom)) {
            throw new InvalidArgumentException('Invalid SalesCreditInvoice list query.');
        }
        $normalized = $search === null ? null : trim($search);
        $this->search = $normalized === '' ? null : $normalized;
    }
}

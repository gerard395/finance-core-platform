<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Shared\Finance\Money;

final readonly class SalesInvoiceReadiness
{
    public function __construct(
        private SalesInvoiceReadinessStatus $status,
        private ?Money $netTotal = null,
        private ?Money $taxTotal = null,
        private ?Money $grossTotal = null,
    ) {}

    public function status(): SalesInvoiceReadinessStatus
    {
        return $this->status;
    }

    public function netTotal(): ?Money
    {
        return $this->netTotal;
    }

    public function taxTotal(): ?Money
    {
        return $this->taxTotal;
    }

    public function grossTotal(): ?Money
    {
        return $this->grossTotal;
    }
}

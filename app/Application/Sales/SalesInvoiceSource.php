<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class SalesInvoiceSource
{
    public function __construct(
        private SalesInvoiceSourceStatus $status,
        private ?SalesInvoiceId $invoiceId = null,
        private ?SalesCustomerSnapshot $customerSnapshot = null,
        private ?SalesAddressSnapshot $invoiceAddressSnapshot = null,
    ) {}

    public function status(): SalesInvoiceSourceStatus
    {
        return $this->status;
    }

    public function invoiceId(): ?SalesInvoiceId
    {
        return $this->invoiceId;
    }

    public function customerSnapshot(): ?SalesCustomerSnapshot
    {
        return $this->customerSnapshot;
    }

    public function invoiceAddressSnapshot(): ?SalesAddressSnapshot
    {
        return $this->invoiceAddressSnapshot;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use LogicException;

final readonly class SalesCustomerContext
{
    private function __construct(
        private SalesCustomerContextStatus $status,
        private ?SalesCustomerSnapshot $customer = null,
        private ?SalesAddressSnapshot $invoiceAddress = null,
    ) {}

    public static function success(SalesCustomerSnapshot $customer, ?SalesAddressSnapshot $invoiceAddress): self
    {
        return new self(SalesCustomerContextStatus::Success, $customer, $invoiceAddress);
    }

    public static function failure(SalesCustomerContextStatus $status): self
    {
        if ($status === SalesCustomerContextStatus::Success) {
            throw new LogicException('Successful Sales customer context requires a snapshot.');
        }

        return new self($status);
    }

    public function status(): SalesCustomerContextStatus
    {
        return $this->status;
    }

    public function customer(): ?SalesCustomerSnapshot
    {
        return $this->customer;
    }

    public function invoiceAddress(): ?SalesAddressSnapshot
    {
        return $this->invoiceAddress;
    }
}

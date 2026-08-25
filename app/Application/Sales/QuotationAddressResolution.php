<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;

final readonly class QuotationAddressResolution
{
    public function __construct(
        private QuotationAddressResolutionStatus $status,
        private ?SalesAddressSnapshot $address = null,
    ) {}

    public function status(): QuotationAddressResolutionStatus
    {
        return $this->status;
    }

    public function address(): ?SalesAddressSnapshot
    {
        return $this->address;
    }
}

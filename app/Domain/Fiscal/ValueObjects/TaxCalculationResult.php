<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Shared\Finance\Money;

final readonly class TaxCalculationResult
{
    public function __construct(
        private Money $netAmount,
        private Money $taxAmount,
        private Money $grossAmount,
        private TaxCodeId $taxCodeId,
        private TaxRate $taxRate,
    ) {}

    public function netAmount(): Money
    {
        return $this->netAmount;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function grossAmount(): Money
    {
        return $this->grossAmount;
    }

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }

    public function taxRate(): TaxRate
    {
        return $this->taxRate;
    }
}

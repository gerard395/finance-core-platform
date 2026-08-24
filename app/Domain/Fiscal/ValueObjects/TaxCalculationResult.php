<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Shared\Finance\Money;

final readonly class TaxCalculationResult
{
    public function __construct(
        private Money $netAmount,
        private Money $taxAmount,
        private Money $grossAmount,
        private TaxCodeId $taxCodeId,
        private TaxRate $taxRate,
        private TaxTreatment $treatment = TaxTreatment::DomesticStandard,
        private VatReturnClassification $vatReturnClassification = VatReturnClassification::DomesticStandard,
        private IcpClassification $icpClassification = IcpClassification::None,
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

    public function treatment(): TaxTreatment
    {
        return $this->treatment;
    }

    public function vatReturnClassification(): VatReturnClassification
    {
        return $this->vatReturnClassification;
    }

    public function icpClassification(): IcpClassification
    {
        return $this->icpClassification;
    }
}

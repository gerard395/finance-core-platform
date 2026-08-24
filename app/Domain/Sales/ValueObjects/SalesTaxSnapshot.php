<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use DomainException;

final readonly class SalesTaxSnapshot
{
    public function __construct(
        private TaxCodeId $taxCodeId,
        private TaxCodeCode $taxCode,
        private TaxCodeName $taxCodeName,
        private TaxRate $taxRate,
        private TaxPostingDirection $direction,
        private TaxTreatment $treatment = TaxTreatment::DomesticStandard,
        private VatReturnClassification $vatReturnClassification = VatReturnClassification::DomesticStandard,
        private IcpClassification $icpClassification = IcpClassification::None,
    ) {
        if ($direction !== TaxPostingDirection::Output) {
            throw new DomainException('A Sales tax snapshot requires output direction.');
        }
        new TaxClassification($treatment, $vatReturnClassification, $icpClassification, $direction);
    }

    public static function fromTaxCode(TaxCode $taxCode): self
    {
        if (! $taxCode->isActive() || $taxCode->direction() !== TaxPostingDirection::Output) {
            throw new DomainException('A Sales tax snapshot requires an active output TaxCode.');
        }

        return new self($taxCode->id(), $taxCode->code(), $taxCode->name(), $taxCode->rate(), $taxCode->direction(), $taxCode->treatment(), $taxCode->vatReturnClassification(), $taxCode->icpClassification());
    }

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }

    public function taxCode(): TaxCodeCode
    {
        return $this->taxCode;
    }

    public function taxCodeName(): TaxCodeName
    {
        return $this->taxCodeName;
    }

    public function taxRate(): TaxRate
    {
        return $this->taxRate;
    }

    public function direction(): TaxPostingDirection
    {
        return $this->direction;
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

    public function forCalculation(): TaxCode
    {
        return new TaxCode($this->taxCodeId, $this->taxCode, $this->taxCodeName, $this->taxRate, $this->direction, TaxCodeStatus::Active, $this->treatment, $this->vatReturnClassification, $this->icpClassification);
    }
}

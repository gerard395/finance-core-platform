<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Entities;

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

final class TaxCode
{
    public function __construct(
        private readonly TaxCodeId $id,
        private readonly TaxCodeCode $code,
        private TaxCodeName $name,
        private TaxRate $rate,
        private readonly TaxPostingDirection $direction,
        private TaxCodeStatus $status,
        private readonly TaxTreatment $treatment = TaxTreatment::DomesticStandard,
        private readonly VatReturnClassification $vatReturnClassification = VatReturnClassification::DomesticStandard,
        private readonly IcpClassification $icpClassification = IcpClassification::None,
    ) {
        new TaxClassification($treatment, $vatReturnClassification, $icpClassification, $direction);
    }

    public function id(): TaxCodeId
    {
        return $this->id;
    }

    public function code(): TaxCodeCode
    {
        return $this->code;
    }

    public function name(): TaxCodeName
    {
        return $this->name;
    }

    public function rate(): TaxRate
    {
        return $this->rate;
    }

    public function status(): TaxCodeStatus
    {
        return $this->status;
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

    public function isActive(): bool
    {
        return $this->status === TaxCodeStatus::Active;
    }

    public function rename(TaxCodeName $name): void
    {
        $this->name = $name;
    }

    public function changeRate(TaxRate $rate): void
    {
        $this->rate = $rate;
    }

    public function activate(): void
    {
        $this->status = TaxCodeStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = TaxCodeStatus::Inactive;
    }
}

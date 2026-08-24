<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;

final readonly class TaxCodeSelectionItem
{
    public function __construct(
        private TaxCodeId $id,
        private TaxCodeCode $code,
        private TaxCodeName $name,
        private TaxRate $rate,
        private TaxPostingDirection $direction,
        private TaxCodeStatus $status,
        private TaxTreatment $treatment = TaxTreatment::DomesticStandard,
        private VatReturnClassification $vatReturnClassification = VatReturnClassification::DomesticStandard,
        private IcpClassification $icpClassification = IcpClassification::None,
    ) {}

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

    public function direction(): TaxPostingDirection
    {
        return $this->direction;
    }

    public function status(): TaxCodeStatus
    {
        return $this->status;
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

    public function toTaxCode(): TaxCode
    {
        return new TaxCode($this->id, $this->code, $this->name, $this->rate, $this->direction, $this->status, $this->treatment, $this->vatReturnClassification, $this->icpClassification);
    }
}

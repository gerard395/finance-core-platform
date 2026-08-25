<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use DomainException;
use InvalidArgumentException;

final readonly class PurchaseTaxSnapshot
{
    public function __construct(public TaxCodeId $id, public TaxCodeCode $code, public TaxCodeName $name, public TaxRate $rate, public TaxPostingDirection $direction, public TaxTreatment $treatment, public VatReturnClassification $vatReturn, public IcpClassification $icp)
    {
        try {
            new TaxClassification($treatment, $vatReturn, $icp, $direction);
        } catch (DomainException $exception) {
            throw new InvalidArgumentException('Purchase line requires a supported domestic Input TaxCode.', previous: $exception);
        }
        if ($direction !== TaxPostingDirection::Input || ! in_array($treatment, [TaxTreatment::DomesticStandard, TaxTreatment::DomesticReduced, TaxTreatment::ZeroRated, TaxTreatment::Exempt, TaxTreatment::OutsideScope], true)) {
            throw new InvalidArgumentException('Purchase line requires a supported domestic Input TaxCode.');
        }
        if (in_array($treatment, [TaxTreatment::Exempt, TaxTreatment::OutsideScope, TaxTreatment::ZeroRated], true) && $rate->value() !== '0') {
            throw new InvalidArgumentException('Zero, exempt and outside-scope purchase TaxCodes require a zero rate.');
        }
    }
}

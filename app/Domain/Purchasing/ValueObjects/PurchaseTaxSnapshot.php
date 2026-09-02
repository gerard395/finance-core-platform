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
    private function __construct(public TaxCodeId $id, public TaxCodeCode $code, public TaxCodeName $name, public TaxRate $rate, public TaxPostingDirection $direction, public TaxTreatment $treatment, public VatReturnClassification $vatReturn, public IcpClassification $icp, public bool $internationalDefinitionAuthoritative)
    {
        if ($internationalDefinitionAuthoritative) {
            return;
        }

        try {
            new TaxClassification($treatment, $vatReturn, $icp, $direction);
        } catch (DomainException $exception) {
            throw new InvalidArgumentException('Purchase line requires a supported domestic Input TaxCode.', previous: $exception);
        }
        $domestic = $direction === TaxPostingDirection::Input && in_array($treatment, [TaxTreatment::DomesticStandard, TaxTreatment::DomesticReduced, TaxTreatment::ZeroRated, TaxTreatment::Exempt, TaxTreatment::OutsideScope], true);
        $internationalSelector = $direction === TaxPostingDirection::Output && in_array($treatment, [TaxTreatment::ReverseChargeEuService, TaxTreatment::IntraCommunityGoods], true);
        if (! $domestic && ! $internationalSelector) {
            throw new InvalidArgumentException('Purchase line requires a supported TaxCode selector.');
        }
        if (in_array($treatment, [TaxTreatment::Exempt, TaxTreatment::OutsideScope, TaxTreatment::ZeroRated], true) && $rate->value() !== '0') {
            throw new InvalidArgumentException('Zero, exempt and outside-scope purchase TaxCodes require a zero rate.');
        }
    }

    public static function legacy(TaxCodeId $id, TaxCodeCode $code, TaxCodeName $name, TaxRate $rate, TaxPostingDirection $direction, TaxTreatment $treatment, VatReturnClassification $vatReturn, IcpClassification $icp): self
    {
        return new self($id, $code, $name, $rate, $direction, $treatment, $vatReturn, $icp, false);
    }

    public static function internationalSelector(TaxCodeId $id, TaxCodeCode $code, TaxCodeName $name, TaxRate $nonAuthoritativeRate, TaxPostingDirection $nonAuthoritativeDirection, TaxTreatment $nonAuthoritativeTreatment, VatReturnClassification $nonAuthoritativeVatReturn, IcpClassification $nonAuthoritativeIcp): self
    {
        return new self($id, $code, $name, $nonAuthoritativeRate, $nonAuthoritativeDirection, $nonAuthoritativeTreatment, $nonAuthoritativeVatReturn, $nonAuthoritativeIcp, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use DomainException;

final readonly class TaxClassification
{
    public function __construct(
        private TaxTreatment $treatment,
        private VatReturnClassification $vatReturn,
        private IcpClassification $icp,
        TaxPostingDirection $direction,
    ) {
        if ($direction === TaxPostingDirection::Input && ! in_array($treatment, [
            TaxTreatment::DomesticStandard,
            TaxTreatment::DomesticReduced,
            TaxTreatment::ZeroRated,
            TaxTreatment::Exempt,
            TaxTreatment::OutsideScope,
        ], true)) {
            throw new DomainException('International Output treatments cannot be used for Input TaxCodes.');
        }

        $expected = match ($treatment) {
            TaxTreatment::DomesticStandard => [VatReturnClassification::DomesticStandard, IcpClassification::None],
            TaxTreatment::DomesticReduced => [VatReturnClassification::DomesticReduced, IcpClassification::None],
            TaxTreatment::ZeroRated => [VatReturnClassification::DomesticZeroRated, IcpClassification::None],
            TaxTreatment::ReverseChargeEuService => [VatReturnClassification::EuServices, IcpClassification::Service],
            TaxTreatment::IntraCommunityGoods => [VatReturnClassification::IntraCommunitySupplies, IcpClassification::GoodsSupply],
            TaxTreatment::Exempt => [VatReturnClassification::Exempt, IcpClassification::None],
            TaxTreatment::OutsideScope => [VatReturnClassification::OutsideScope, IcpClassification::None],
        };

        if ($expected !== [$vatReturn, $icp]) {
            throw new DomainException('Tax treatment, VAT-return and ICP classifications are incompatible.');
        }
    }

    public function treatment(): TaxTreatment
    {
        return $this->treatment;
    }

    public function vatReturn(): VatReturnClassification
    {
        return $this->vatReturn;
    }

    public function icp(): IcpClassification
    {
        return $this->icp;
    }
}

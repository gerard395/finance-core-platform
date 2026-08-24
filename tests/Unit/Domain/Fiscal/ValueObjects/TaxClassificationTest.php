<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxClassification;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxClassificationTest extends TestCase
{
    #[DataProvider('validOutputCombinations')]
    public function test_it_accepts_only_canonical_combinations(TaxTreatment $treatment, VatReturnClassification $vat, IcpClassification $icp): void
    {
        $classification = new TaxClassification($treatment, $vat, $icp, TaxPostingDirection::Output);
        self::assertSame($treatment, $classification->treatment());
        self::assertSame($vat, $classification->vatReturn());
        self::assertSame($icp, $classification->icp());
    }

    public static function validOutputCombinations(): array
    {
        return [
            [TaxTreatment::DomesticStandard, VatReturnClassification::DomesticStandard, IcpClassification::None],
            [TaxTreatment::DomesticReduced, VatReturnClassification::DomesticReduced, IcpClassification::None],
            [TaxTreatment::ZeroRated, VatReturnClassification::DomesticZeroRated, IcpClassification::None],
            [TaxTreatment::ReverseChargeEuService, VatReturnClassification::EuServices, IcpClassification::Service],
            [TaxTreatment::IntraCommunityGoods, VatReturnClassification::IntraCommunitySupplies, IcpClassification::GoodsSupply],
            [TaxTreatment::Exempt, VatReturnClassification::Exempt, IcpClassification::None],
            [TaxTreatment::OutsideScope, VatReturnClassification::OutsideScope, IcpClassification::None],
        ];
    }

    public function test_rate_zero_never_implies_icp_and_invalid_combinations_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        new TaxClassification(TaxTreatment::ZeroRated, VatReturnClassification::EuServices, IcpClassification::Service, TaxPostingDirection::Output);
    }

    public function test_international_treatment_is_not_available_for_input_codes(): void
    {
        $this->expectException(DomainException::class);
        new TaxClassification(TaxTreatment::ReverseChargeEuService, VatReturnClassification::EuServices, IcpClassification::Service, TaxPostingDirection::Input);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Sales;

use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Identity\Uuid;
use App\Presentation\Sales\SalesTaxCodePresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesTaxCodePresenterTest extends TestCase
{
    #[DataProvider('labels')]
    public function test_labels_are_treatment_driven_and_distinguish_zero_tax_meanings(TaxTreatment $treatment, VatReturnClassification $vat, IcpClassification $icp, string $label): void
    {
        $item = new TaxCodeSelectionItem(new TaxCodeId(new Uuid('10000000-0000-4000-8000-000000000001')), new TaxCodeCode('SELECTOR'), new TaxCodeName('Technical name'), new TaxRate('0'), TaxPostingDirection::Output, TaxCodeStatus::Active, $treatment, $vat, $icp);

        self::assertSame($label, SalesTaxCodePresenter::label($item));
    }

    public static function labels(): array
    {
        return [
            'zero rated' => [TaxTreatment::ZeroRated, VatReturnClassification::DomesticZeroRated, IcpClassification::None, 'BTW 0%'],
            'EU service' => [TaxTreatment::ReverseChargeEuService, VatReturnClassification::EuServices, IcpClassification::Service, 'Btw verlegd – dienst EU'],
            'EU goods' => [TaxTreatment::IntraCommunityGoods, VatReturnClassification::IntraCommunitySupplies, IcpClassification::GoodsSupply, 'Intracommunautaire levering goederen'],
            'outside scope' => [TaxTreatment::OutsideScope, VatReturnClassification::OutsideScope, IcpClassification::None, 'Buiten Nederlandse btw-heffing'],
            'exempt' => [TaxTreatment::Exempt, VatReturnClassification::Exempt, IcpClassification::None, 'Vrijgesteld'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Domain\Fiscal\Enums\TaxTreatment;

final readonly class SalesTaxCodePresenter
{
    public static function label(TaxCodeSelectionItem $taxCode): string
    {
        return match ($taxCode->treatment()) {
            TaxTreatment::DomesticStandard, TaxTreatment::DomesticReduced => "BTW {$taxCode->rate()->value()}% – Nederland",
            TaxTreatment::ZeroRated => 'BTW 0%',
            TaxTreatment::ReverseChargeEuService => 'Btw verlegd – dienst EU',
            TaxTreatment::IntraCommunityGoods => 'Intracommunautaire levering goederen',
            TaxTreatment::OutsideScope => 'Buiten Nederlandse btw-heffing',
            TaxTreatment::Exempt => 'Vrijgesteld',
        };
    }
}

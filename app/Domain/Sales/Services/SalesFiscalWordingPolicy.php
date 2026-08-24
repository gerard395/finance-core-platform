<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Sales\Enums\SalesFiscalWording;

final readonly class SalesFiscalWordingPolicy
{
    public function forTreatment(TaxTreatment $treatment): SalesFiscalWording
    {
        return match ($treatment) {
            TaxTreatment::ReverseChargeEuService => SalesFiscalWording::VatReverseCharged,
            TaxTreatment::IntraCommunityGoods => SalesFiscalWording::IntraCommunityGoodsSupply,
            default => SalesFiscalWording::None,
        };
    }
}

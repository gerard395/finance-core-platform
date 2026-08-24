<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxTreatment: string
{
    case DomesticStandard = 'domestic_standard';
    case DomesticReduced = 'domestic_reduced';
    case ZeroRated = 'zero_rated';
    case ReverseChargeEuService = 'reverse_charge_eu_service';
    case IntraCommunityGoods = 'intra_community_goods';
    case Exempt = 'exempt';
    case OutsideScope = 'outside_scope';
}

<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum VatReturnClassification: string
{
    case DomesticStandard = 'domestic_standard';
    case DomesticReduced = 'domestic_reduced';
    case DomesticZeroRated = 'domestic_zero_rated';
    case EuServices = 'eu_services';
    case IntraCommunitySupplies = 'intra_community_supplies';
    case Exempt = 'exempt';
    case OutsideScope = 'outside_scope';
}

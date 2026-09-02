<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxTreatmentType: string
{
    case DomesticSupplierVat = 'domestic_supplier_vat';
    case EuGoodsAcquisitionNl = 'eu_goods_acquisition_nl';
    case EuB2bGeneralRuleService = 'eu_b2b_general_rule_service';
    case NonEuB2bGeneralRuleService = 'non_eu_b2b_general_rule_service';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutsideScope = 'outside_scope';
}

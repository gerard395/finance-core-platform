<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxReportingClassification: string
{
    case DomesticInput = 'domestic_input';
    case EuAcquisitionDue4b = 'eu_acquisition_due_4b';
    case EuGeneralServiceDue4b = 'eu_general_service_due_4b';
    case NonEuGeneralServiceDue4a = 'non_eu_general_service_due_4a';
    case DeductibleInput5b = 'deductible_input_5b';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutsideScope = 'outside_scope';
}

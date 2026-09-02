<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxLegRole: string
{
    case VatPayable = 'vat_payable';
    case VatDeductible = 'vat_deductible';
}

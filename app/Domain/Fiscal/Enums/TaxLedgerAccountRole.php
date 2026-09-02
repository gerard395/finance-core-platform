<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxLedgerAccountRole: string
{
    case VatPayableControl = 'vat_payable_control';
    case InputVatControl = 'input_vat_control';
}

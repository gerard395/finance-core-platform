<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum SupplierVatMode: string
{
    case SupplierCharged = 'supplier_charged';
    case SelfAssessed = 'self_assessed';
}

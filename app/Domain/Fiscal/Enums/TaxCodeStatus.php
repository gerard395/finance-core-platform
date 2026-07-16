<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxCodeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

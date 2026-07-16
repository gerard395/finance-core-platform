<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum LedgerAccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

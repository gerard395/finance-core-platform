<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingPeriodLockMode
{
    case None;
    case Shared;
    case Exclusive;
}

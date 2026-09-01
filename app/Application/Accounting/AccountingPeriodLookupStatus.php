<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingPeriodLookupStatus
{
    case Found;
    case NoPeriod;
    case IntegrityFailure;
}

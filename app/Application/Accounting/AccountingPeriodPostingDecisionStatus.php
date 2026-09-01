<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingPeriodPostingDecisionStatus
{
    case Open;
    case Closed;
    case NoPeriod;
    case IntegrityFailure;
}

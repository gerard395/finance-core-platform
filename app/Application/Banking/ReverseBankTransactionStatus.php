<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum ReverseBankTransactionStatus
{
    case Success;
    case NotFound;
    case NotPosted;
    case AlreadyReversed;
    case FinancialStateInvalid;
    case PeriodClosed;
    case NoAccountingPeriod;
    case PeriodIntegrityFailure;
    case PostingFailure;
}

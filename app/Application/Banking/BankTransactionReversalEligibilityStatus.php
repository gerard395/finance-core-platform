<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankTransactionReversalEligibilityStatus
{
    case Eligible;
    case NotPosted;
    case AlreadyReversed;
    case FinancialStateInvalid;
    case NotFound;
}

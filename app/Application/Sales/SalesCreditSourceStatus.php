<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesCreditSourceStatus
{
    case Success;
    case NotFound;
    case SourceNotPosted;
    case FinancialPostingMissing;
    case ReversalSourceMissing;
    case ReversalSourceInvalid;
    case AlreadyCredited;
}

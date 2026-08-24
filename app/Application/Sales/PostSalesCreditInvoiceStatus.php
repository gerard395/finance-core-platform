<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum PostSalesCreditInvoiceStatus
{
    case Success;
    case NotFound;
    case InvalidState;
    case AlreadyPosted;
    case ConfigurationMissing;
    case ConfigurationInvalid;
    case SourceFinancialStateInvalid;
    case FinancialStateInconsistent;
    case PostingFailure;
}

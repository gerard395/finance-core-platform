<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum PostSalesInvoiceStatus
{
    case Success;
    case NotFound;
    case InvalidState;
    case AlreadyPosted;
    case ConfigurationMissing;
    case ConfigurationInvalid;
    case FinancialStateInconsistent;
    case PostingFailure;
}

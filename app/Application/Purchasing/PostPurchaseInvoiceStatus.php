<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PostPurchaseInvoiceStatus
{
    case Success;
    case AlreadyPosted;
    case NotFound;
    case InvalidState;
    case ConfigurationMissing;
    case ConfigurationInvalid;
    case FiscalStateInvalid;
    case PostingFailure;
}

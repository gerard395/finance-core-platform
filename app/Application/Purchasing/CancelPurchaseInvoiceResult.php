<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum CancelPurchaseInvoiceResult
{
    case Success;
    case AlreadyCancelled;
    case NotFound;
    case InvalidState;
}

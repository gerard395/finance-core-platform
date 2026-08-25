<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum UpdateDraftPurchaseInvoiceResult
{
    case Saved;
    case NotFound;
    case InvalidState;
    case InvalidLineReference;
    case DuplicateSupplierInvoice;
}

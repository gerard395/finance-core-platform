<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum CreatePurchaseInvoiceStatus
{
    case Success;
    case DuplicateSupplierInvoice;
    case SupplierNotFound;
    case InvalidSupplier;
    case InvalidLineReference;
}

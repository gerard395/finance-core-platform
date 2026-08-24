<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum OrderInvoicingFactAppendResult
{
    case Appended;
    case AlreadyExists;
    case NotFound;
    case QuantityExceedsAvailable;
    case InvalidFactState;
    case PersistenceConflict;
}

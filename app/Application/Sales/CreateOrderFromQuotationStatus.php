<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum CreateOrderFromQuotationStatus
{
    case Success;
    case NotFound;
    case InvalidSourceState;
    case AlreadyConverted;
    case SequenceMissing;
    case SequenceInactive;
    case DuplicateIdentity;
    case PersistenceConflict;
}

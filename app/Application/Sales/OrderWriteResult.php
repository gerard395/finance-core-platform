<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum OrderWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
    case DuplicateNumber;
    case AlreadyConverted;
    case CustomerNotFound;
    case InactiveCustomer;
    case SourceQuotationNotFound;
    case SourceQuotationInvalid;
    case SequenceMissing;
    case SequenceInactive;
    case InvalidState;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesCreditInvoiceWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
    case DuplicateNumber;
    case AlreadyCredited;
    case SourceNotPosted;
    case FinancialPostingMissing;
    case ReversalSourceMissing;
    case ReversalSourceInvalid;
    case InvalidState;
    case SequenceMissing;
    case SequenceInactive;
}

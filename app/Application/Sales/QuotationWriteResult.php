<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum QuotationWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
    case DuplicateNumber;
    case CustomerNotFound;
    case InactiveCustomer;
    case QuotationAddressNotFound;
    case InactiveQuotationAddress;
    case InvalidQuotationAddressPurpose;
    case SequenceMissing;
    case SequenceInactive;
    case InvalidState;
}

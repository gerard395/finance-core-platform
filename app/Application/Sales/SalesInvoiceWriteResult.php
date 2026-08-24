<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoiceWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
    case DuplicateNumber;
    case CustomerNotFound;
    case InactiveCustomer;
    case MissingInvoiceAddress;
    case TaxCodeNotFound;
    case TaxCodeInactive;
    case WrongTaxDirection;
    case TaxCalculationFailure;
    case CustomerVatIdMissing;
    case CustomerJurisdictionMissing;
    case SupplierVatIdMissing;
    case SupplierJurisdictionMissing;
    case SupplyDateMissing;
    case InvalidState;
    case SequenceMissing;
    case SequenceInactive;
    case ReservationStateInconsistent;
    case AllocationConflict;
    case PersistenceConflict;
}

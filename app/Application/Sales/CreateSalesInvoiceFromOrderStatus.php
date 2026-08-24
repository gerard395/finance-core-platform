<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum CreateSalesInvoiceFromOrderStatus
{
    case Success;
    case AlreadyCreated;
    case NotFound;
    case InvalidOrderState;
    case NothingToInvoice;
    case QuantityExceedsRemaining;
    case MissingInvoiceAddress;
    case TaxCodeNotFound;
    case TaxCodeInactive;
    case TaxCodeWrongDirection;
    case TaxCalculationFailed;
    case CustomerVatIdMissing;
    case CustomerJurisdictionMissing;
    case SupplierVatIdMissing;
    case SupplierJurisdictionMissing;
    case SupplyDateMissing;
    case SequenceMissing;
    case SequenceInactive;
    case PersistenceConflict;
    case RequestConflict;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoiceReadinessStatus
{
    case Ready;
    case MissingCustomerSnapshot;
    case MissingInvoiceAddress;
    case MissingLines;
    case MissingTaxSnapshot;
    case TaxCalculationFailed;
    case CustomerVatIdMissing;
    case CustomerJurisdictionMissing;
    case SupplierVatIdMissing;
    case SupplierJurisdictionMissing;
    case SupplyDateMissing;
}

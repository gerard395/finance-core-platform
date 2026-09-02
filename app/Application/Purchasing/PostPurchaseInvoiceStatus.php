<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PostPurchaseInvoiceStatus
{
    case Success;
    case AlreadyPosted;
    case NotFound;
    case InvalidState;
    case ConfigurationMissing;
    case ConfigurationInvalid;
    case FiscalStateInvalid;
    case UnsupportedTaxTreatment;
    case MissingTaxTreatment;
    case MissingTaxConfiguration;
    case InvalidDeductibility;
    case IncompleteFiscalPartyFacts;
    case UnsupportedForeignVat;
    case UnsupportedImportCustoms;
    case PeriodClosed;
    case NoAccountingPeriod;
    case PeriodIntegrityFailure;
    case PostingFailure;
}

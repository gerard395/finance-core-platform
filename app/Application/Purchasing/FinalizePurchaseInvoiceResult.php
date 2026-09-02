<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum FinalizePurchaseInvoiceResult
{
    case Success;
    case AlreadyFinalized;
    case NotFound;
    case InvalidState;
    case ValidationFailed;
    case MissingTaxTreatment;
    case TaxTreatmentIntegrityFailure;
    case UnsupportedTaxTreatment;
    case InvalidDeductibility;
    case IncompleteFiscalPartyFacts;
    case UnsupportedForeignVat;
    case UnsupportedImportCustoms;
}

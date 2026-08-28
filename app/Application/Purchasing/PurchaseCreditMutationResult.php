<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PurchaseCreditMutationResult: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case InvalidSource = 'invalid_source';
    case InvalidLines = 'invalid_lines';
    case DuplicateSupplierCreditInvoice = 'duplicate_supplier_credit_invoice';
    case InvalidState = 'invalid_state';
    case AlreadyFinalized = 'already_finalized';
    case AlreadyCancelled = 'already_cancelled';
}

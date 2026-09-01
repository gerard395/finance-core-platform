<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PostPurchaseCreditInvoiceStatus: string
{
    case Success = 'success';
    case AlreadyPosted = 'already_posted';
    case NotFound = 'not_found';
    case InvalidState = 'invalid_state';
    case SourceLineAlreadyCredited = 'source_line_already_credited';
    case FinancialStateInvalid = 'financial_state_invalid';
    case PeriodClosed = 'period_closed';
    case NoAccountingPeriod = 'no_accounting_period';
    case PeriodIntegrityFailure = 'period_integrity_failure';
    case PostingFailure = 'posting_failure';
}

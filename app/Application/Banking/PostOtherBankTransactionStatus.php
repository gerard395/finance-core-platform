<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum PostOtherBankTransactionStatus: string
{
    case Success = 'success';
    case AlreadyPosted = 'already_posted';
    case NotFound = 'not_found';
    case InvalidAmount = 'invalid_amount';
    case InvalidContraAccount = 'invalid_contra_account';
    case MissingPostingConfiguration = 'missing_posting_configuration';
    case PeriodClosed = 'period_closed';
    case NoAccountingPeriod = 'no_accounting_period';
    case PeriodIntegrityFailure = 'period_integrity_failure';
    case PostingFailure = 'posting_failure';
}

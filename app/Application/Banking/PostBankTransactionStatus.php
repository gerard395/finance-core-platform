<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum PostBankTransactionStatus: string
{
    case Success = 'success';
    case AlreadyPosted = 'already_posted';
    case NotFound = 'not_found';
    case InvalidState = 'invalid_state';
    case ConfigurationMissing = 'configuration_missing';
    case ConfigurationInvalid = 'configuration_invalid';
    case AllocationExceedsOpenBalance = 'allocation_exceeds_open_balance';
    case FinancialStateInvalid = 'financial_state_invalid';
    case PostingFailure = 'posting_failure';
}

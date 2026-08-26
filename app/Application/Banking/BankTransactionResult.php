<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankTransactionResult: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case InvalidReference = 'invalid_reference';
    case InvalidAllocation = 'invalid_allocation';
    case AlreadyFinalized = 'already_finalized';
    case InvalidState = 'invalid_state';
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankEntrySuggestionOutcome: string
{
    case PaymentReady = 'payment_ready';
    case AllocationIncomplete = 'allocation_incomplete';
    case Ambiguous = 'ambiguous';
    case Other = 'other';
}

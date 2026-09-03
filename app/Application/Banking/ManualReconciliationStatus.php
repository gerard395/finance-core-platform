<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum ManualReconciliationStatus: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case AlreadyIgnored = 'already_ignored';
    case NotIgnored = 'not_ignored';
    case AlreadyReconciled = 'already_reconciled';
    case IntegrityFailure = 'integrity_failure';
    case ConcurrencyConflict = 'concurrency_conflict';
}

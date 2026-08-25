<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum DeliveryOutcomeResolutionStatus: string
{
    case Resolved = 'resolved';
    case AlreadyResolved = 'already_resolved';
    case NotFound = 'not_found';
    case InvalidAttemptStatus = 'invalid_attempt_status';
    case Unauthorized = 'unauthorized';
}

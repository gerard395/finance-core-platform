<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum PeriodTransitionResult: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case InvalidState = 'invalid_state';
    case IntegrityFailure = 'integrity_failure';
}

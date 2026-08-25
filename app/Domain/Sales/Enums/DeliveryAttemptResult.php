<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum DeliveryAttemptResult: string
{
    case Attempting = 'attempting';
    case AcceptedByTransport = 'accepted_by_transport';
    case FailedConfiguration = 'failed_configuration';
    case FailedValidation = 'failed_validation';
    case FailedTransport = 'failed_transport';
    case OutcomeUnknown = 'outcome_unknown';
}

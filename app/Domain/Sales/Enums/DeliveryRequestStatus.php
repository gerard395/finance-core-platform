<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum DeliveryRequestStatus: string
{
    case Requested = 'requested';
    case Prepared = 'prepared';
    case Attempting = 'attempting';
    case AcceptedByTransport = 'accepted_by_transport';
    case Failed = 'failed';
    case OutcomeUnknown = 'outcome_unknown';
}

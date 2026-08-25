<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum CreateDeliveryRequestStatus: string
{
    case Created = 'created';
    case Existing = 'existing';
    case IdempotencyConflict = 'idempotency_conflict';
    case NotFound = 'not_found';
    case InvalidArtifact = 'invalid_artifact';
    case MissingRecipient = 'missing_recipient';
    case MissingSender = 'missing_sender';
}

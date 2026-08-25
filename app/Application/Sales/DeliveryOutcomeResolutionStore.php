<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\DeliveryOutcomeResolution;

interface DeliveryOutcomeResolutionStore
{
    public function appendForUnknownAttempt(DeliveryOutcomeResolution $resolution): DeliveryOutcomeResolutionStatus;
}

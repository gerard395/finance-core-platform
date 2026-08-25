<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;

interface DeliveryIdentityGenerator
{
    public function attemptId(): DeliveryAttemptId;

    public function outboxId(): DeliveryOutboxMessageId;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\DeliveryAttempt;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;

final readonly class ClaimedDelivery
{
    public function __construct(public DeliveryOutboxMessageId $outboxId, public DeliveryRequest $request, public DeliveryAttempt $attempt) {}
}

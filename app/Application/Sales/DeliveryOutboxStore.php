<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;

interface DeliveryOutboxStore
{
    public function claim(DeliveryOutboxMessageId $outboxId): ?ClaimedDelivery;

    public function complete(ClaimedDelivery $delivery, DocumentMailTransportResult $result): void;

    public function markOutcomeUnknown(DeliveryOutboxMessageId $outboxId, string $category): void;
}

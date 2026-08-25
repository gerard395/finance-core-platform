<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class QueueSalesDocumentDeliveryResult
{
    public function __construct(public SalesDocumentDeliveryReadinessStatus $status, public bool $replayed = false) {}

    public function queued(): bool
    {
        return $this->status === SalesDocumentDeliveryReadinessStatus::Ready;
    }
}

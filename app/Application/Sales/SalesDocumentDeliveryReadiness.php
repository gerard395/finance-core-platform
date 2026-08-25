<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class SalesDocumentDeliveryReadiness
{
    public function __construct(
        public SalesDocumentDeliveryReadinessStatus $status,
        public SalesDocumentDeliveryHistory $history,
    ) {}

    public function ready(): bool
    {
        return $this->status === SalesDocumentDeliveryReadinessStatus::Ready;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderId;

final readonly class CreateOrderFromQuotationResult
{
    private function __construct(private CreateOrderFromQuotationStatus $status, private ?OrderId $orderId = null) {}

    public static function success(OrderId $orderId): self
    {
        return new self(CreateOrderFromQuotationStatus::Success, $orderId);
    }

    public static function forStatus(CreateOrderFromQuotationStatus $status, ?OrderId $orderId = null): self
    {
        return new self($status, $orderId);
    }

    public function status(): CreateOrderFromQuotationStatus
    {
        return $this->status;
    }

    public function orderId(): ?OrderId
    {
        return $this->orderId;
    }
}

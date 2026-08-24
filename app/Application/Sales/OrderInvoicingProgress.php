<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;

final readonly class OrderInvoicingProgress
{
    /** @param list<OrderInvoicingProgressLine> $lines */
    public function __construct(private OrderId $orderId, private OrderStatus $status, private array $lines) {}

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    /** @return list<OrderInvoicingProgressLine> */
    public function lines(): array
    {
        return $this->lines;
    }
}

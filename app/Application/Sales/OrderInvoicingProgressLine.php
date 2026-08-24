<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderInvoiceQuantityBalance;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;

final readonly class OrderInvoicingProgressLine
{
    public function __construct(private OrderLineId $orderLineId, private Quantity $ordered, private OrderInvoiceQuantityBalance $reserved, private OrderInvoiceQuantityBalance $allocated, private OrderInvoiceQuantityBalance $available) {}

    public function orderLineId(): OrderLineId
    {
        return $this->orderLineId;
    }

    public function ordered(): Quantity
    {
        return $this->ordered;
    }

    public function reserved(): OrderInvoiceQuantityBalance
    {
        return $this->reserved;
    }

    public function allocated(): OrderInvoiceQuantityBalance
    {
        return $this->allocated;
    }

    public function available(): OrderInvoiceQuantityBalance
    {
        return $this->available;
    }
}

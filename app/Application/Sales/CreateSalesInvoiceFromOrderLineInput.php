<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;

final readonly class CreateSalesInvoiceFromOrderLineInput
{
    public function __construct(private OrderLineId $orderLineId, private Quantity $quantity, private TaxCodeId $taxCodeId) {}

    public function orderLineId(): OrderLineId
    {
        return $this->orderLineId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;

final readonly class OrderInvoiceAllocation
{
    public function __construct(private OrderInvoiceAllocationId $id, private AdministrationId $administrationId, private OrderInvoiceReservationId $reservationId, private OrderId $orderId, private OrderLineId $orderLineId, private SalesInvoiceId $salesInvoiceId, private SalesInvoiceLineId $salesInvoiceLineId, private Quantity $quantity) {}

    public function id(): OrderInvoiceAllocationId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function reservationId(): OrderInvoiceReservationId
    {
        return $this->reservationId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function orderLineId(): OrderLineId
    {
        return $this->orderLineId;
    }

    public function salesInvoiceId(): SalesInvoiceId
    {
        return $this->salesInvoiceId;
    }

    public function salesInvoiceLineId(): SalesInvoiceLineId
    {
        return $this->salesInvoiceLineId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }
}

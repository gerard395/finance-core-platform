<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;

final readonly class OrderInvoiceReservation
{
    public function __construct(private OrderInvoiceReservationId $id, private AdministrationId $administrationId, private OrderInvoiceDraftRequestId $draftRequestId, private OrderId $orderId, private OrderLineId $orderLineId, private SalesInvoiceId $salesInvoiceId, private SalesInvoiceLineId $salesInvoiceLineId, private Quantity $quantity) {}

    public function id(): OrderInvoiceReservationId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function draftRequestId(): OrderInvoiceDraftRequestId
    {
        return $this->draftRequestId;
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

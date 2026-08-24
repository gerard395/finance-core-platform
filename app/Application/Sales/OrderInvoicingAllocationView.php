<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;

final readonly class OrderInvoicingAllocationView
{
    public function __construct(public OrderInvoiceAllocationId $id, public OrderInvoiceReservationId $reservationId, public OrderId $orderId, public OrderLineId $orderLineId, public SalesInvoiceId $salesInvoiceId, public SalesInvoiceLineId $salesInvoiceLineId, public Quantity $quantity) {}
}

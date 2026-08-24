<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;

interface OrderInvoicingProgressReader
{
    public function progress(AdministrationId $administrationId, OrderId $orderId): ?OrderInvoicingProgress;

    /** @return list<OrderInvoicingReservationView> */
    public function activeReservationsForOrder(AdministrationId $administrationId, OrderId $orderId): array;

    /** @return list<OrderInvoicingReservationView> */
    public function activeReservationsForLine(AdministrationId $administrationId, OrderId $orderId, OrderLineId $lineId): array;

    /** @return list<OrderInvoicingReservationView> */
    public function reservationsForDraftRequest(AdministrationId $administrationId, OrderInvoiceDraftRequestId $requestId): array;

    /** @return list<OrderInvoicingReservationView> */
    public function reservationsForSalesInvoice(AdministrationId $administrationId, SalesInvoiceId $invoiceId): array;

    /** @return list<OrderInvoicingAllocationView> */
    public function allocationsForOrder(AdministrationId $administrationId, OrderId $orderId): array;

    /** @return list<OrderInvoicingAllocationView> */
    public function allocationsForLine(AdministrationId $administrationId, OrderId $orderId, OrderLineId $lineId): array;

    /** @return list<OrderInvoicingAllocationView> */
    public function allocationsForSalesInvoice(AdministrationId $administrationId, SalesInvoiceId $invoiceId): array;

    /** @return list<OrderInvoicingAllocationView> */
    public function allocationsForSalesInvoiceLine(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineId $lineId): array;
}

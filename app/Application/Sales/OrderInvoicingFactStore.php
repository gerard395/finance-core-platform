<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\OrderInvoiceAllocation;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\Entities\OrderInvoiceReservation;
use App\Domain\Sales\Entities\OrderInvoiceReservationRelease;

interface OrderInvoicingFactStore
{
    public function appendDraftRequest(OrderInvoiceDraftRequest $request): OrderInvoicingFactAppendResult;

    public function appendReservation(OrderInvoiceReservation $reservation): OrderInvoicingFactAppendResult;

    public function appendRelease(OrderInvoiceReservationRelease $release): OrderInvoicingFactAppendResult;

    public function appendAllocation(OrderInvoiceAllocation $allocation): OrderInvoicingFactAppendResult;
}

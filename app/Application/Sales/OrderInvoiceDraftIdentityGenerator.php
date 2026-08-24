<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;

interface OrderInvoiceDraftIdentityGenerator
{
    public function salesInvoiceId(): SalesInvoiceId;

    public function salesInvoiceLineId(): SalesInvoiceLineId;

    public function reservationId(): OrderInvoiceReservationId;
}

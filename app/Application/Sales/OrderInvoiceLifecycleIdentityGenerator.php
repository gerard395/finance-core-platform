<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationReleaseId;

interface OrderInvoiceLifecycleIdentityGenerator
{
    public function allocationId(): OrderInvoiceAllocationId;

    public function releaseId(): OrderInvoiceReservationReleaseId;
}

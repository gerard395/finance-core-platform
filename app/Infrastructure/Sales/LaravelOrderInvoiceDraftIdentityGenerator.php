<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\OrderInvoiceDraftIdentityGenerator;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelOrderInvoiceDraftIdentityGenerator implements OrderInvoiceDraftIdentityGenerator
{
    public function salesInvoiceId(): SalesInvoiceId
    {
        return new SalesInvoiceId(new Uuid(Str::uuid()->toString()));
    }

    public function salesInvoiceLineId(): SalesInvoiceLineId
    {
        return new SalesInvoiceLineId(new Uuid(Str::uuid()->toString()));
    }

    public function reservationId(): OrderInvoiceReservationId
    {
        return new OrderInvoiceReservationId(new Uuid(Str::uuid()->toString()));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationReleaseId;

final readonly class OrderInvoiceReservationRelease
{
    public function __construct(private OrderInvoiceReservationReleaseId $id, private AdministrationId $administrationId, private OrderInvoiceReservationId $reservationId) {}

    public function id(): OrderInvoiceReservationReleaseId
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
}

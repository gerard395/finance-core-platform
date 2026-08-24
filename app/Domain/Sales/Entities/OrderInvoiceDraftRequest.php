<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class OrderInvoiceDraftRequest
{
    public function __construct(private OrderInvoiceDraftRequestId $id, private AdministrationId $administrationId, private OrderId $orderId, private SalesInvoiceId $salesInvoiceId) {}

    public function id(): OrderInvoiceDraftRequestId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function salesInvoiceId(): SalesInvoiceId
    {
        return $this->salesInvoiceId;
    }
}

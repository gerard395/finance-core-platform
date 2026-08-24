<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;

interface OrderInvoiceDraftRequestReader
{
    public function find(AdministrationId $administrationId, OrderInvoiceDraftRequestId $requestId): ?OrderInvoiceDraftRequest;
}

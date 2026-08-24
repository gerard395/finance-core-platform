<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;

interface OrderInvoicingOrderLocker
{
    public function lock(AdministrationId $administrationId, OrderId $orderId): bool;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\ValueObjects\OrderId;

interface OrderInvoicingSource
{
    public function findLockedForAdministration(AdministrationId $administrationId, OrderId $orderId): ?Order;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Order;

interface OrderUpdater
{
    public function update(AdministrationId $administrationId, Order $order): OrderWriteResult;
}

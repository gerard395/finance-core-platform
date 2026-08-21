<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;

interface OrderDetailReadRepository
{
    public function find(AdministrationId $administrationId, OrderId $orderId): ?OrderDetail;
}

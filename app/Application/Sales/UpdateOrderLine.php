<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\ValueObjects\OrderId;

final readonly class UpdateOrderLine
{
    public function __construct(private OrderMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, OrderId $orderId, OrderLine $line): OrderWriteResult
    {
        return $this->mutations->mutate($administrationId, $orderId, static fn ($order) => $order->updateLine($line));
    }
}

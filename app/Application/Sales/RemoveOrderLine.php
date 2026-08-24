<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use DomainException;

final readonly class RemoveOrderLine
{
    public function __construct(private OrderMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, OrderId $orderId, OrderLineId $lineId): OrderWriteResult
    {
        return $this->mutations->mutate($administrationId, $orderId, static function ($order) use ($lineId): void {
            if (! $order->hasLine($lineId)) {
                throw new DomainException('Order line does not exist.');
            }
            $order->removeLine($lineId);
        });
    }
}

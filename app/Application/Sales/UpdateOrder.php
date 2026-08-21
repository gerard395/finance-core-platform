<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\OrderId;
use DateTimeImmutable;

final readonly class UpdateOrder
{
    public function __construct(private OrderMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, OrderId $orderId, DateTimeImmutable $date): OrderWriteResult
    {
        return $this->mutations->mutate($administrationId, $orderId, static fn ($order) => $order->changeOrderDate($date));
    }
}

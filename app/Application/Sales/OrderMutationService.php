<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\ValueObjects\OrderId;
use Closure;
use DomainException;
use InvalidArgumentException;

final readonly class OrderMutationService
{
    public function __construct(private OrderReadRepository $reader, private OrderUpdater $updater, private TransactionManager $transactions) {}

    /** @param Closure(Order): void $mutation */
    public function mutate(AdministrationId $administrationId, OrderId $orderId, Closure $mutation): OrderWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $orderId, $mutation): OrderWriteResult {
            $order = $this->reader->findForAdministration($administrationId, $orderId);
            if ($order === null) {
                return OrderWriteResult::NotFound;
            }
            try {
                $mutation($order);
            } catch (DomainException|InvalidArgumentException) {
                return OrderWriteResult::InvalidState;
            }

            return $this->updater->update($administrationId, $order);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;

final readonly class CreateOrder
{
    public function __construct(private SalesCustomerContextReader $customers, private SalesNumberAllocator $numbers, private QuotationReadRepository $quotations, private OrderCreator $orders, private TransactionManager $transactions) {}

    /** @param list<OrderLine> $lines */
    public function execute(AdministrationId $administrationId, OrderId $orderId, CustomerId $customerId, Currency $currency, DateTimeImmutable $orderDate, ?QuotationId $sourceQuotationId = null, array $lines = []): OrderWriteResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $orderId, $customerId, $currency, $orderDate, $sourceQuotationId, $lines): OrderWriteResult {
                $context = $this->customers->read($administrationId, $customerId, null);
                if ($context->status() === SalesCustomerContextStatus::NotFound) {
                    return OrderWriteResult::CustomerNotFound;
                }
                if ($context->status() === SalesCustomerContextStatus::InactiveCustomer) {
                    return OrderWriteResult::InactiveCustomer;
                }
                $snapshot = $context->customer();
                if ($snapshot === null) {
                    return OrderWriteResult::CustomerNotFound;
                }
                if ($sourceQuotationId !== null) {
                    $source = $this->quotations->findForAdministration($administrationId, $sourceQuotationId);
                    if ($source === null) {
                        return OrderWriteResult::SourceQuotationNotFound;
                    }
                    if ($source->status() !== QuotationStatus::Accepted || ! $source->customerId()->equals($customerId) || ! $source->currency()->equals($currency) || $source->customerSnapshot() === null || ! $source->customerSnapshot()->customerId()->equals($snapshot->customerId())) {
                        return OrderWriteResult::SourceQuotationInvalid;
                    }
                }
                $allocation = $this->numbers->next($administrationId, SalesNumberType::Order);
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return OrderWriteResult::SequenceMissing;
                }
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return OrderWriteResult::SequenceInactive;
                }
                $number = $allocation->number();
                if (! $number instanceof OrderNumber) {
                    return OrderWriteResult::SequenceMissing;
                }
                $order = new Order($orderId, $number, $administrationId, $customerId, $currency, $orderDate, $sourceQuotationId, OrderStatus::Draft, $snapshot);
                foreach ($lines as $line) {
                    $order->addLine($line);
                }
                $result = $this->orders->create($administrationId, $order);
                if ($result !== OrderWriteResult::Success) {
                    throw new OrderPersistenceConflict($result);
                }

                return OrderWriteResult::Success;
            });
        } catch (OrderPersistenceConflict $conflict) {
            return $conflict->result();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use DateTimeImmutable;
use RuntimeException;

final readonly class CreateOrderFromQuotation
{
    public function __construct(
        private TransactionManager $transactions,
        private QuotationOrderConversionSource $quotations,
        private OrderBySourceQuotationRepository $convertedOrders,
        private SalesNumberAllocator $numbers,
        private QuotationOrderConversionIdentityGenerator $identities,
        private OrderCreator $orders,
    ) {}

    public function execute(AdministrationId $administrationId, QuotationId $quotationId, DateTimeImmutable $orderDate): CreateOrderFromQuotationResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $quotationId, $orderDate): CreateOrderFromQuotationResult {
                $quotation = $this->quotations->findLockedForAdministration($administrationId, $quotationId);
                if ($quotation === null) {
                    return CreateOrderFromQuotationResult::forStatus(CreateOrderFromQuotationStatus::NotFound);
                }
                if ($quotation->status() !== QuotationStatus::Accepted || $quotation->customerSnapshot() === null) {
                    return CreateOrderFromQuotationResult::forStatus(CreateOrderFromQuotationStatus::InvalidSourceState);
                }
                $existing = $this->convertedOrders->findBySourceQuotation($administrationId, $quotationId);
                if ($existing !== null) {
                    return CreateOrderFromQuotationResult::forStatus(CreateOrderFromQuotationStatus::AlreadyConverted, $existing->id());
                }
                $allocation = $this->numbers->next($administrationId, SalesNumberType::Order);
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return CreateOrderFromQuotationResult::forStatus(CreateOrderFromQuotationStatus::SequenceMissing);
                }
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return CreateOrderFromQuotationResult::forStatus(CreateOrderFromQuotationStatus::SequenceInactive);
                }
                $number = $allocation->number();
                if (! $number instanceof OrderNumber) {
                    throw new QuotationOrderConversionFailed(CreateOrderFromQuotationStatus::PersistenceConflict);
                }
                $order = new Order(
                    $this->identities->orderId(), $number, $administrationId, $quotation->customerId(), $quotation->currency(),
                    $orderDate, $quotationId, OrderStatus::Draft, $quotation->customerSnapshot(),
                );
                foreach ($quotation->lines() as $line) {
                    $order->addLine(new OrderLine($this->identities->orderLineId(), $line->description(), $line->quantity(), $line->unitPrice()));
                }
                if (! $order->total()->equals($quotation->total())) {
                    throw new QuotationOrderConversionFailed(CreateOrderFromQuotationStatus::PersistenceConflict);
                }
                $result = $this->orders->create($administrationId, $order);
                if ($result !== OrderWriteResult::Success) {
                    throw new QuotationOrderConversionFailed(match ($result) {
                        OrderWriteResult::AlreadyConverted => CreateOrderFromQuotationStatus::AlreadyConverted,
                        OrderWriteResult::DuplicateIdentity => CreateOrderFromQuotationStatus::DuplicateIdentity,
                        default => CreateOrderFromQuotationStatus::PersistenceConflict,
                    });
                }

                return CreateOrderFromQuotationResult::success($order->id());
            });
        } catch (QuotationOrderConversionFailed $failure) {
            $existing = $failure->status === CreateOrderFromQuotationStatus::AlreadyConverted
                ? $this->convertedOrders->findBySourceQuotation($administrationId, $quotationId)
                : null;

            return CreateOrderFromQuotationResult::forStatus($failure->status, $existing?->id());
        }
    }
}

final class QuotationOrderConversionFailed extends RuntimeException
{
    public function __construct(public readonly CreateOrderFromQuotationStatus $status)
    {
        parent::__construct($status->name);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;

final class Order
{
    public function __construct(
        private readonly OrderId $id,
        private readonly OrderNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $orderDate,
        private readonly ?QuotationId $sourceQuotationId,
        private OrderStatus $status,
    ) {}

    public function id(): OrderId
    {
        return $this->id;
    }

    public function number(): OrderNumber
    {
        return $this->number;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function orderDate(): DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function sourceQuotationId(): ?QuotationId
    {
        return $this->sourceQuotationId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->transitionTo(OrderStatus::Confirmed, [OrderStatus::Draft]);
    }

    public function markPartiallyInvoiced(): void
    {
        $this->transitionTo(OrderStatus::PartiallyInvoiced, [OrderStatus::Confirmed]);
    }

    public function markFullyInvoiced(): void
    {
        $this->transitionTo(OrderStatus::FullyInvoiced, [
            OrderStatus::Confirmed,
            OrderStatus::PartiallyInvoiced,
        ]);
    }

    public function cancel(): void
    {
        $this->transitionTo(OrderStatus::Cancelled, [
            OrderStatus::Draft,
            OrderStatus::Confirmed,
        ]);
    }

    /** @param list<OrderStatus> $allowedFrom */
    private function transitionTo(OrderStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Order cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }
}

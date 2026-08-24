<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class OrderDetail
{
    /** @param list<OrderLine> $lines */
    public function __construct(private OrderId $id, private OrderNumber $number, private SalesCustomerSnapshot $customer, private Currency $currency, private OrderStatus $status, private DateTimeImmutable $orderDate, private ?QuotationId $sourceQuotationId, private array $lines, private Money $total) {}

    public static function fromOrder(Order $order): self
    {
        $snapshot = $order->customerSnapshot();
        if ($snapshot === null) {
            throw new \DomainException('Persistent Order requires a Customer snapshot.');
        }

        return new self($order->id(), $order->number(), $snapshot, $order->currency(), $order->status(), $order->orderDate(), $order->sourceQuotationId(), $order->lines(), $order->total());
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function number(): OrderNumber
    {
        return $this->number;
    }

    public function customer(): SalesCustomerSnapshot
    {
        return $this->customer;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function orderDate(): DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function sourceQuotationId(): ?QuotationId
    {
        return $this->sourceQuotationId;
    }

    /** @return list<OrderLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function total(): Money
    {
        return $this->total;
    }
}

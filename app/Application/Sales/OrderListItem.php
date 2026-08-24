<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class OrderListItem
{
    public function __construct(private OrderId $id, private OrderNumber $number, private DisplayName $customerName, private DateTimeImmutable $orderDate, private OrderStatus $status, private Currency $currency, private Money $netTotal, private ?QuotationId $sourceQuotationId) {}

    public function id(): OrderId
    {
        return $this->id;
    }

    public function number(): OrderNumber
    {
        return $this->number;
    }

    public function customerName(): DisplayName
    {
        return $this->customerName;
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

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function netTotal(): Money
    {
        return $this->netTotal;
    }
}

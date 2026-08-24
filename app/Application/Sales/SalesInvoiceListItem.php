<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class SalesInvoiceListItem
{
    public function __construct(private SalesInvoiceId $id, private SalesInvoiceNumber $number, private DisplayName $customerName, private DateTimeImmutable $invoiceDate, private DateTimeImmutable $dueDate, private SalesInvoiceStatus $status, private Currency $currency, private Money $netTotal, private Money $taxTotal, private Money $grossTotal, private ?OrderId $sourceOrderId) {}

    public function id(): SalesInvoiceId
    {
        return $this->id;
    }

    public function number(): SalesInvoiceNumber
    {
        return $this->number;
    }

    public function customerName(): DisplayName
    {
        return $this->customerName;
    }

    public function invoiceDate(): DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function status(): SalesInvoiceStatus
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

    public function taxTotal(): Money
    {
        return $this->taxTotal;
    }

    public function grossTotal(): Money
    {
        return $this->grossTotal;
    }

    public function sourceOrderId(): ?OrderId
    {
        return $this->sourceOrderId;
    }
}

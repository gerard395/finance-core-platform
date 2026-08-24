<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class QuotationListItem
{
    public function __construct(private QuotationId $id, private QuotationNumber $number, private DisplayName $customerName, private DateTimeImmutable $quotationDate, private ?DateTimeImmutable $expiryDate, private QuotationStatus $status, private Currency $currency, private Money $netTotal) {}

    public function id(): QuotationId
    {
        return $this->id;
    }

    public function number(): QuotationNumber
    {
        return $this->number;
    }

    public function customerName(): DisplayName
    {
        return $this->customerName;
    }

    public function quotationDate(): DateTimeImmutable
    {
        return $this->quotationDate;
    }

    public function expiryDate(): ?DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function status(): QuotationStatus
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

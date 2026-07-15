<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Quotation
{
    public function __construct(
        private readonly QuotationId $id,
        private readonly QuotationNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private QuotationStatus $status,
        private readonly DateTimeImmutable $quotationDate,
        private readonly ?DateTimeImmutable $expiryDate,
    ) {
        if ($expiryDate !== null && $expiryDate < $quotationDate) {
            throw new InvalidArgumentException('Expiry date cannot precede quotation date.');
        }
    }

    public function id(): QuotationId
    {
        return $this->id;
    }

    public function number(): QuotationNumber
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

    public function status(): QuotationStatus
    {
        return $this->status;
    }

    public function quotationDate(): DateTimeImmutable
    {
        return $this->quotationDate;
    }

    public function expiryDate(): ?DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function send(): void
    {
        $this->transition(QuotationStatus::Draft, QuotationStatus::Sent);
    }

    public function accept(): void
    {
        $this->transition(QuotationStatus::Sent, QuotationStatus::Accepted);
    }

    public function reject(): void
    {
        $this->transition(QuotationStatus::Sent, QuotationStatus::Rejected);
    }

    public function expire(): void
    {
        if ($this->status === QuotationStatus::Expired) {
            return;
        }

        if (! in_array($this->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)) {
            throw new DomainException('Quotation cannot expire from its current status.');
        }

        $this->status = QuotationStatus::Expired;
    }

    private function transition(QuotationStatus $from, QuotationStatus $to): void
    {
        if ($this->status === $to) {
            return;
        }

        if ($this->status !== $from) {
            throw new DomainException("Quotation cannot transition from {$this->status->value} to {$to->value}.");
        }

        $this->status = $to;
    }
}

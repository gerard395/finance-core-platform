<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;

final class SalesCreditInvoice
{
    public function __construct(
        private readonly SalesCreditInvoiceId $id,
        private readonly SalesCreditInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $creditInvoiceDate,
        private readonly ?SalesInvoiceId $sourceInvoiceId,
        private SalesCreditInvoiceStatus $status,
    ) {}

    public function id(): SalesCreditInvoiceId
    {
        return $this->id;
    }

    public function number(): SalesCreditInvoiceNumber
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

    public function creditInvoiceDate(): DateTimeImmutable
    {
        return $this->creditInvoiceDate;
    }

    public function sourceInvoiceId(): ?SalesInvoiceId
    {
        return $this->sourceInvoiceId;
    }

    public function status(): SalesCreditInvoiceStatus
    {
        return $this->status;
    }

    public function finalize(): void
    {
        $this->transitionTo(SalesCreditInvoiceStatus::Finalized, [SalesCreditInvoiceStatus::Draft]);
    }

    public function post(): void
    {
        $this->transitionTo(SalesCreditInvoiceStatus::Posted, [SalesCreditInvoiceStatus::Finalized]);
    }

    public function cancel(): void
    {
        $this->transitionTo(SalesCreditInvoiceStatus::Cancelled, [
            SalesCreditInvoiceStatus::Draft,
            SalesCreditInvoiceStatus::Finalized,
        ]);
    }

    /** @param list<SalesCreditInvoiceStatus> $allowedFrom */
    private function transitionTo(SalesCreditInvoiceStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Sales credit invoice cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }
}

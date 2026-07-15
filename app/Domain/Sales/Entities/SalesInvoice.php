<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class SalesInvoice
{
    public function __construct(
        private readonly SalesInvoiceId $id,
        private readonly SalesInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $invoiceDate,
        private readonly DateTimeImmutable $dueDate,
        private readonly ?OrderId $sourceOrderId,
        private SalesInvoiceStatus $status,
    ) {
        if ($dueDate < $invoiceDate) {
            throw new InvalidArgumentException('Due date cannot precede invoice date.');
        }
    }

    public function id(): SalesInvoiceId
    {
        return $this->id;
    }

    public function number(): SalesInvoiceNumber
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

    public function invoiceDate(): DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function sourceOrderId(): ?OrderId
    {
        return $this->sourceOrderId;
    }

    public function status(): SalesInvoiceStatus
    {
        return $this->status;
    }

    public function finalize(): void
    {
        $this->transitionTo(SalesInvoiceStatus::Finalized, [SalesInvoiceStatus::Draft]);
    }

    public function post(): void
    {
        $this->transitionTo(SalesInvoiceStatus::Posted, [SalesInvoiceStatus::Finalized]);
    }

    public function markAsPaid(): void
    {
        $this->transitionTo(SalesInvoiceStatus::Paid, [SalesInvoiceStatus::Posted]);
    }

    public function cancel(): void
    {
        $this->transitionTo(SalesInvoiceStatus::Cancelled, [
            SalesInvoiceStatus::Draft,
            SalesInvoiceStatus::Finalized,
        ]);
    }

    /** @param list<SalesInvoiceStatus> $allowedFrom */
    private function transitionTo(SalesInvoiceStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Sales invoice cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }
}

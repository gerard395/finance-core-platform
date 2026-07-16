<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\SupplierReference;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class PurchaseInvoice
{
    public function __construct(
        private readonly PurchaseInvoiceId $id,
        private readonly PurchaseInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly SupplierId $supplierId,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $invoiceDate,
        private readonly DateTimeImmutable $dueDate,
        private readonly ?SupplierReference $supplierReference,
        private PurchaseInvoiceStatus $status,
    ) {
        if ($dueDate < $invoiceDate) {
            throw new InvalidArgumentException('Due date cannot precede invoice date.');
        }
    }

    public function id(): PurchaseInvoiceId
    {
        return $this->id;
    }

    public function number(): PurchaseInvoiceNumber
    {
        return $this->number;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplierId;
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

    public function supplierReference(): ?SupplierReference
    {
        return $this->supplierReference;
    }

    public function status(): PurchaseInvoiceStatus
    {
        return $this->status;
    }

    public function finalize(): void
    {
        $this->transitionTo(PurchaseInvoiceStatus::Finalized, [PurchaseInvoiceStatus::Draft]);
    }

    public function post(): void
    {
        $this->transitionTo(PurchaseInvoiceStatus::Posted, [PurchaseInvoiceStatus::Finalized]);
    }

    public function markAsPaid(): void
    {
        $this->transitionTo(PurchaseInvoiceStatus::Paid, [PurchaseInvoiceStatus::Posted]);
    }

    public function cancel(): void
    {
        $this->transitionTo(PurchaseInvoiceStatus::Cancelled, [
            PurchaseInvoiceStatus::Draft,
            PurchaseInvoiceStatus::Finalized,
        ]);
    }

    /** @param list<PurchaseInvoiceStatus> $allowedFrom */
    private function transitionTo(PurchaseInvoiceStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Purchase invoice cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }
}

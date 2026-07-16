<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\SupplierReference;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class PurchaseInvoice
{
    /** @var array<string, PurchaseInvoiceLine> */
    private array $lines = [];

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

    /** @return list<PurchaseInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function line(PurchaseInvoiceLineId $lineId): ?PurchaseInvoiceLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function hasLine(PurchaseInvoiceLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function addLine(PurchaseInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $key = $line->id()->toString();

        if (isset($this->lines[$key])) {
            throw new DomainException('Purchase invoice already contains a line with this identity.');
        }

        $this->lines[$key] = $line;
    }

    public function removeLine(PurchaseInvoiceLineId $lineId): void
    {
        $this->assertDraftForLineChanges();
        unset($this->lines[$lineId->toString()]);
    }

    public function finalize(): void
    {
        if ($this->status === PurchaseInvoiceStatus::Draft && $this->lines === []) {
            throw new DomainException('Purchase invoice must contain at least one line before it can be finalized.');
        }

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

    private function assertDraftForLineChanges(): void
    {
        if ($this->status !== PurchaseInvoiceStatus::Draft) {
            throw new DomainException('Purchase invoice lines can only be changed while the purchase invoice is in draft.');
        }
    }
}

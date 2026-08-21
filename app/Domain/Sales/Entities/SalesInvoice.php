<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class SalesInvoice
{
    /** @var array<string, SalesInvoiceLine> */
    private array $lines = [];

    public function __construct(
        private readonly SalesInvoiceId $id,
        private readonly SalesInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private DateTimeImmutable $invoiceDate,
        private DateTimeImmutable $dueDate,
        private readonly ?OrderId $sourceOrderId,
        private SalesInvoiceStatus $status,
        private readonly ?SalesCustomerSnapshot $customerSnapshot = null,
        private readonly ?SalesAddressSnapshot $invoiceAddressSnapshot = null,
    ) {
        self::assertDates($invoiceDate, $dueDate);
        self::assertSnapshots($customerId, $customerSnapshot, $invoiceAddressSnapshot);
    }

    /** @param list<SalesInvoiceLine> $lines */
    public static function reconstitute(
        SalesInvoiceId $id,
        SalesInvoiceNumber $number,
        AdministrationId $administrationId,
        CustomerId $customerId,
        Currency $currency,
        DateTimeImmutable $invoiceDate,
        DateTimeImmutable $dueDate,
        ?OrderId $sourceOrderId,
        SalesInvoiceStatus $status,
        array $lines,
        ?SalesCustomerSnapshot $customerSnapshot = null,
        ?SalesAddressSnapshot $invoiceAddressSnapshot = null,
    ): self {
        $invoice = new self($id, $number, $administrationId, $customerId, $currency, $invoiceDate, $dueDate, $sourceOrderId, $status, $customerSnapshot, $invoiceAddressSnapshot);
        $invoice->restoreLines($lines);

        if (in_array($status, [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Posted, SalesInvoiceStatus::Paid], true) && $lines === []) {
            throw new DomainException('A finalized, posted or paid sales invoice must contain at least one line.');
        }

        return $invoice;
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

    public function customerSnapshot(): ?SalesCustomerSnapshot
    {
        return $this->customerSnapshot;
    }

    public function invoiceAddressSnapshot(): ?SalesAddressSnapshot
    {
        return $this->invoiceAddressSnapshot;
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

    /** @return list<SalesInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function hasLine(SalesInvoiceLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function line(SalesInvoiceLineId $lineId): ?SalesInvoiceLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function addLine(SalesInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();

        if (isset($this->lines[$key])) {
            throw new DomainException('Sales invoice already contains a line with this identity.');
        }

        $this->lines[$key] = $line;
    }

    public function updateLine(SalesInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();
        if (! isset($this->lines[$key])) {
            throw new DomainException('Sales invoice line to update does not exist.');
        }
        $this->lines[$key] = $line;
    }

    public function removeLine(SalesInvoiceLineId $lineId): void
    {
        $this->assertDraftForLineChanges();
        unset($this->lines[$lineId->toString()]);
    }

    public function changeDates(DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate): void
    {
        $this->assertDraftForLineChanges();
        self::assertDates($invoiceDate, $dueDate);
        $this->invoiceDate = $invoiceDate;
        $this->dueDate = $dueDate;
    }

    public function total(): Money
    {
        $total = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $total = $total->add($line->lineTotal());
        }

        return $total;
    }

    public function finalize(): void
    {
        if ($this->status === SalesInvoiceStatus::Draft && $this->lines === []) {
            throw new DomainException('Sales invoice must contain at least one line before it can be finalized.');
        }

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

    private function assertDraftForLineChanges(): void
    {
        if ($this->status !== SalesInvoiceStatus::Draft) {
            throw new DomainException('Sales invoice lines can only be changed while the sales invoice is in draft.');
        }
    }

    /** @param list<SalesInvoiceLine> $lines */
    private function restoreLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->assertLineCurrency($line);
            $key = $line->id()->toString();
            if (isset($this->lines[$key])) {
                throw new DomainException('Sales invoice already contains a line with this identity.');
            }
            $this->lines[$key] = $line;
        }
    }

    private function assertLineCurrency(SalesInvoiceLine $line): void
    {
        if (! $line->unitPrice()->currency()->equals($this->currency)) {
            throw new DomainException('Sales invoice line currency must match document currency.');
        }
    }

    private static function assertDates(DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate): void
    {
        if ($dueDate < $invoiceDate) {
            throw new InvalidArgumentException('Due date cannot precede invoice date.');
        }
    }

    private static function assertSnapshots(CustomerId $customerId, ?SalesCustomerSnapshot $customer, ?SalesAddressSnapshot $address): void
    {
        if ($customer !== null && ! $customer->customerId()->equals($customerId)) {
            throw new DomainException('Sales invoice customer snapshot must match CustomerId.');
        }
        if ($address !== null && $address->type() !== AddressType::Invoice) {
            throw new DomainException('Sales invoice requires an Invoice address snapshot.');
        }
    }
}

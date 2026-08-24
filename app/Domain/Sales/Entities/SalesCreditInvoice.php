<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesCustomerFiscalSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesSupplierFiscalSnapshot;
use App\Domain\Sales\ValueObjects\SupplyDate;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final class SalesCreditInvoice
{
    /** @var array<string, SalesCreditInvoiceLine> */
    private array $lines = [];

    public function __construct(
        private readonly SalesCreditInvoiceId $id,
        private readonly SalesCreditInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private DateTimeImmutable $creditInvoiceDate,
        private readonly SalesInvoiceId $sourceInvoiceId,
        private SalesCreditInvoiceStatus $status,
        private readonly ?SalesCustomerSnapshot $customerSnapshot = null,
        private readonly ?SalesAddressSnapshot $invoiceAddressSnapshot = null,
        private readonly ?SalesCustomerFiscalSnapshot $customerFiscalSnapshot = null,
        private readonly ?SalesSupplierFiscalSnapshot $supplierFiscalSnapshot = null,
        private readonly ?SupplyDate $originalSupplyDate = null,
    ) {
        if ($customerSnapshot !== null && ! $customerSnapshot->customerId()->equals($customerId)) {
            throw new DomainException('Sales credit invoice customer snapshot must match CustomerId.');
        }
        if ($invoiceAddressSnapshot !== null && $invoiceAddressSnapshot->type() !== AddressType::Invoice) {
            throw new DomainException('Sales credit invoice requires an Invoice address snapshot.');
        }
    }

    /** @param list<SalesCreditInvoiceLine> $lines */
    public static function reconstitute(
        SalesCreditInvoiceId $id,
        SalesCreditInvoiceNumber $number,
        AdministrationId $administrationId,
        CustomerId $customerId,
        Currency $currency,
        DateTimeImmutable $creditInvoiceDate,
        SalesInvoiceId $sourceInvoiceId,
        SalesCreditInvoiceStatus $status,
        array $lines,
        ?SalesCustomerSnapshot $customerSnapshot = null,
        ?SalesAddressSnapshot $invoiceAddressSnapshot = null,
        ?SalesCustomerFiscalSnapshot $customerFiscalSnapshot = null,
        ?SalesSupplierFiscalSnapshot $supplierFiscalSnapshot = null,
        ?SupplyDate $originalSupplyDate = null,
    ): self {
        $creditInvoice = new self($id, $number, $administrationId, $customerId, $currency, $creditInvoiceDate, $sourceInvoiceId, $status, $customerSnapshot, $invoiceAddressSnapshot, $customerFiscalSnapshot, $supplierFiscalSnapshot, $originalSupplyDate);
        $creditInvoice->restoreLines($lines);

        if (in_array($status, [SalesCreditInvoiceStatus::Finalized, SalesCreditInvoiceStatus::Posted], true) && $lines === []) {
            throw new DomainException('A finalized or posted sales credit invoice must contain at least one line.');
        }

        return $creditInvoice;
    }

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

    public function sourceInvoiceId(): SalesInvoiceId
    {
        return $this->sourceInvoiceId;
    }

    public function customerSnapshot(): ?SalesCustomerSnapshot
    {
        return $this->customerSnapshot;
    }

    public function invoiceAddressSnapshot(): ?SalesAddressSnapshot
    {
        return $this->invoiceAddressSnapshot;
    }

    public function customerFiscalSnapshot(): ?SalesCustomerFiscalSnapshot
    {
        return $this->customerFiscalSnapshot;
    }

    public function supplierFiscalSnapshot(): ?SalesSupplierFiscalSnapshot
    {
        return $this->supplierFiscalSnapshot;
    }

    public function originalSupplyDate(): ?SupplyDate
    {
        return $this->originalSupplyDate;
    }

    public function status(): SalesCreditInvoiceStatus
    {
        return $this->status;
    }

    /** @return list<SalesCreditInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function hasLine(SalesCreditInvoiceLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function line(SalesCreditInvoiceLineId $lineId): ?SalesCreditInvoiceLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function addLine(SalesCreditInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();

        if (isset($this->lines[$key])) {
            throw new DomainException('Sales credit invoice already contains a line with this identity.');
        }

        $this->lines[$key] = $line;
    }

    public function updateLine(SalesCreditInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();
        if (! isset($this->lines[$key])) {
            throw new DomainException('Sales credit invoice line to update does not exist.');
        }
        $this->lines[$key] = $line;
    }

    public function removeLine(SalesCreditInvoiceLineId $lineId): void
    {
        $this->assertDraftForLineChanges();
        unset($this->lines[$lineId->toString()]);
    }

    public function changeCreditInvoiceDate(DateTimeImmutable $creditInvoiceDate): void
    {
        $this->assertDraftForLineChanges();
        $this->creditInvoiceDate = $creditInvoiceDate;
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
        if ($this->status === SalesCreditInvoiceStatus::Draft && $this->lines === []) {
            throw new DomainException('Sales credit invoice must contain at least one line before it can be finalized.');
        }

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

    private function assertDraftForLineChanges(): void
    {
        if ($this->status !== SalesCreditInvoiceStatus::Draft) {
            throw new DomainException('Sales credit invoice lines can only be changed while the sales credit invoice is in draft.');
        }
    }

    /** @param list<SalesCreditInvoiceLine> $lines */
    private function restoreLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->assertLineCurrency($line);
            $key = $line->id()->toString();
            if (isset($this->lines[$key])) {
                throw new DomainException('Sales credit invoice already contains a line with this identity.');
            }
            $this->lines[$key] = $line;
        }
    }

    private function assertLineCurrency(SalesCreditInvoiceLine $line): void
    {
        if (! $line->unitPrice()->currency()->equals($this->currency)) {
            throw new DomainException('Sales credit invoice line currency must match document currency.');
        }
    }
}

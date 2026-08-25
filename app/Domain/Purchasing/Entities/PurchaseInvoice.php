<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class PurchaseInvoice
{
    /** @var array<string, PurchaseInvoiceLine> */
    private array $lines = [];

    /** @param list<PurchaseInvoiceLine> $lines */
    public function __construct(private readonly PurchaseInvoiceId $id, private SupplierInvoiceNumber $supplierInvoiceNumber, private readonly AdministrationId $administrationId, private readonly PurchaseSupplierSnapshot $supplier, private readonly Currency $currency, private DateTimeImmutable $supplierInvoiceDate, private DateTimeImmutable $receivedDate, private ?DateTimeImmutable $supplyDate, private DateTimeImmutable $fiscalReportingDate, private DateTimeImmutable $dueDate, private PurchaseDocumentAddress $documentAddress, private PurchaseInvoiceStatus $status = PurchaseInvoiceStatus::Draft, array $lines = [], private ?UserId $finalizedBy = null, private ?DateTimeImmutable $finalizedAt = null)
    {
        $this->assertHeader();
        if ($status === PurchaseInvoiceStatus::Draft && ($finalizedBy !== null || $finalizedAt !== null)) {
            throw new InvalidArgumentException('Draft cannot have finalization facts.');
        }
        if (in_array($status, [PurchaseInvoiceStatus::Finalized, PurchaseInvoiceStatus::Posted], true) && ($finalizedBy === null || $finalizedAt === null)) {
            throw new InvalidArgumentException('Finalized or Posted requires finalization facts.');
        }
        foreach ($lines as $line) {
            $this->addReconstitutedLine($line);
        }
    }

    public function id(): PurchaseInvoiceId
    {
        return $this->id;
    }

    public function number(): SupplierInvoiceNumber
    {
        return $this->supplierInvoiceNumber;
    }

    public function supplierInvoiceNumber(): SupplierInvoiceNumber
    {
        return $this->supplierInvoiceNumber;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplier->supplierId;
    }

    public function supplierSnapshot(): PurchaseSupplierSnapshot
    {
        return $this->supplier;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function invoiceDate(): DateTimeImmutable
    {
        return $this->supplierInvoiceDate;
    }

    public function supplierInvoiceDate(): DateTimeImmutable
    {
        return $this->supplierInvoiceDate;
    }

    public function receivedDate(): DateTimeImmutable
    {
        return $this->receivedDate;
    }

    public function supplyDate(): ?DateTimeImmutable
    {
        return $this->supplyDate;
    }

    public function fiscalReportingDate(): DateTimeImmutable
    {
        return $this->fiscalReportingDate;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function documentAddress(): PurchaseDocumentAddress
    {
        return $this->documentAddress;
    }

    public function status(): PurchaseInvoiceStatus
    {
        return $this->status;
    }

    public function finalizedBy(): ?UserId
    {
        return $this->finalizedBy;
    }

    public function finalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    /** @return list<PurchaseInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function line(PurchaseInvoiceLineId $id): ?PurchaseInvoiceLine
    {
        return $this->lines[$id->toString()] ?? null;
    }

    public function hasLine(PurchaseInvoiceLineId $id): bool
    {
        return isset($this->lines[$id->toString()]);
    }

    /** @param list<PurchaseInvoiceLine> $lines */
    public function replaceDraft(SupplierInvoiceNumber $number, DateTimeImmutable $invoiceDate, DateTimeImmutable $receivedDate, ?DateTimeImmutable $supplyDate, DateTimeImmutable $dueDate, PurchaseDocumentAddress $address, array $lines): void
    {
        $this->assertDraft();
        $this->supplierInvoiceNumber = $number;
        $this->supplierInvoiceDate = $invoiceDate;
        $this->receivedDate = $receivedDate;
        $this->supplyDate = $supplyDate;
        $this->dueDate = $dueDate;
        $this->fiscalReportingDate = max($invoiceDate, $receivedDate);
        $this->documentAddress = $address;
        $this->lines = [];
        $this->assertHeader();
        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    public function addLine(PurchaseInvoiceLine $line): void
    {
        $this->assertDraft();
        $this->assertLine($line);
        $this->addReconstitutedLine($line);
    }

    public function removeLine(PurchaseInvoiceLineId $id): void
    {
        $this->assertDraft();
        unset($this->lines[$id->toString()]);
    }

    public function finalize(UserId $actor, DateTimeImmutable $at): bool
    {
        if ($this->status === PurchaseInvoiceStatus::Finalized) {
            return false;
        } $this->assertDraft();
        if ($this->lines === []) {
            throw new DomainException('Purchase invoice requires at least one line.');
        } $this->finalizedBy = $actor;
        $this->finalizedAt = $at;
        $this->status = PurchaseInvoiceStatus::Finalized;

        return true;
    }

    public function markPosted(): bool
    {
        if ($this->status === PurchaseInvoiceStatus::Posted) {
            return false;
        }
        if ($this->status !== PurchaseInvoiceStatus::Finalized) {
            throw new DomainException('Only a Finalized purchase invoice can be posted.');
        }
        $this->status = PurchaseInvoiceStatus::Posted;

        return true;
    }

    public function cancel(): bool
    {
        if ($this->status === PurchaseInvoiceStatus::Cancelled) {
            return false;
        } if (! in_array($this->status, [PurchaseInvoiceStatus::Draft, PurchaseInvoiceStatus::Finalized], true)) {
            throw new DomainException('Purchase invoice cannot be cancelled.');
        } $this->status = PurchaseInvoiceStatus::Cancelled;

        return true;
    }

    public function netTotal(): Money
    {
        return $this->sum('net');
    }

    public function taxTotal(): Money
    {
        return $this->sum('taxAmount');
    }

    public function grossTotal(): Money
    {
        return $this->sum('gross');
    }

    private function sum(string $method): Money
    {
        $total = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $total = $total->add($line->{$method}());
        }

        return $total;
    }

    private function assertDraft(): void
    {
        if ($this->status !== PurchaseInvoiceStatus::Draft) {
            throw new DomainException('Purchase invoice can only be changed while Draft.');
        }
    }

    private function assertHeader(): void
    {
        if ($this->currency->code() !== 'EUR' || $this->dueDate < $this->supplierInvoiceDate || $this->fiscalReportingDate != max($this->supplierInvoiceDate, $this->receivedDate)) {
            throw new InvalidArgumentException('Purchase invoice date or EUR contract is invalid.');
        }
    }

    private function assertLine(PurchaseInvoiceLine $line): void
    {
        if (! $line->unitPrice()->currency()->equals($this->currency)) {
            throw new DomainException('Line currency differs from document currency.');
        }
    }

    private function addReconstitutedLine(PurchaseInvoiceLine $line): void
    {
        $key = $line->id()->toString();
        if (isset($this->lines[$key])) {
            throw new DomainException('Duplicate line identity.');
        } $this->assertLine($line);
        $this->lines[$key] = $line;
    }
}

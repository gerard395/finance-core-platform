<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final class PurchaseCreditInvoice
{
    /** @var array<string, PurchaseCreditInvoiceLine> */
    private array $lines = [];

    /** @param list<PurchaseCreditInvoiceLine> $lines */
    public function __construct(private readonly PurchaseCreditInvoiceId $id, private PurchaseCreditInvoiceNumber $number, private readonly AdministrationId $administrationId, private readonly SupplierId $supplierId, private readonly Currency $currency, private DateTimeImmutable $creditInvoiceDate, private readonly ?PurchaseInvoiceId $sourcePurchaseInvoiceId, private PurchaseCreditInvoiceStatus $status, private readonly ?PurchaseSupplierSnapshot $supplier = null, private readonly ?PurchaseDocumentAddress $documentAddress = null, private ?DateTimeImmutable $receivedDate = null, private ?DateTimeImmutable $fiscalReportingDate = null, private readonly ?DateTimeImmutable $sourceSupplyDate = null, private readonly ?OpenItemId $sourcePayableOpenItemId = null, private readonly ?UserId $createdBy = null, private readonly ?DateTimeImmutable $createdAt = null, array $lines = [], private ?UserId $finalizedBy = null, private ?DateTimeImmutable $finalizedAt = null, private ?UserId $cancelledBy = null, private ?DateTimeImmutable $cancelledAt = null)
    {
        $this->receivedDate ??= $creditInvoiceDate;
        $this->fiscalReportingDate ??= max($creditInvoiceDate, $this->receivedDate);
        foreach ($lines as $line) {
            $this->addReconstitutedLine($line);
        }
    }

    public function id(): PurchaseCreditInvoiceId
    {
        return $this->id;
    }

    public function number(): PurchaseCreditInvoiceNumber
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

    public function supplierSnapshot(): ?PurchaseSupplierSnapshot
    {
        return $this->supplier;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function creditInvoiceDate(): DateTimeImmutable
    {
        return $this->creditInvoiceDate;
    }

    public function supplierCreditDate(): DateTimeImmutable
    {
        return $this->creditInvoiceDate;
    }

    public function receivedDate(): DateTimeImmutable
    {
        return $this->receivedDate;
    }

    public function fiscalReportingDate(): DateTimeImmutable
    {
        return $this->fiscalReportingDate;
    }

    public function sourceSupplyDate(): ?DateTimeImmutable
    {
        return $this->sourceSupplyDate;
    }

    public function sourcePurchaseInvoiceId(): ?PurchaseInvoiceId
    {
        return $this->sourcePurchaseInvoiceId;
    }

    public function sourcePayableOpenItemId(): ?OpenItemId
    {
        return $this->sourcePayableOpenItemId;
    }

    public function documentAddress(): ?PurchaseDocumentAddress
    {
        return $this->documentAddress;
    }

    public function status(): PurchaseCreditInvoiceStatus
    {
        return $this->status;
    }

    public function createdBy(): ?UserId
    {
        return $this->createdBy;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function finalizedBy(): ?UserId
    {
        return $this->finalizedBy;
    }

    public function finalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function cancelledBy(): ?UserId
    {
        return $this->cancelledBy;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /** @return list<PurchaseCreditInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function line(PurchaseCreditInvoiceLineId $id): ?PurchaseCreditInvoiceLine
    {
        return $this->lines[$id->toString()] ?? null;
    }

    public function hasLine(PurchaseCreditInvoiceLineId $id): bool
    {
        return isset($this->lines[$id->toString()]);
    }

    public function replaceDraft(PurchaseCreditInvoiceNumber $number, DateTimeImmutable $creditDate, DateTimeImmutable $receivedDate, array $lines): void
    {
        $this->assertDraft();
        $this->number = $number;
        $this->creditInvoiceDate = $creditDate;
        $this->receivedDate = $receivedDate;
        $this->fiscalReportingDate = max($creditDate, $receivedDate);
        $this->lines = [];
        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    public function addLine(PurchaseCreditInvoiceLine $line): void
    {
        $this->assertDraft();
        $this->addReconstitutedLine($line);
    }

    public function removeLine(PurchaseCreditInvoiceLineId $id): void
    {
        $this->assertDraft();
        unset($this->lines[$id->toString()]);
    }

    public function finalize(?UserId $actor = null, ?DateTimeImmutable $at = null): void
    {
        if ($this->status === PurchaseCreditInvoiceStatus::Finalized) {
            return;
        }
        if ($this->status !== PurchaseCreditInvoiceStatus::Draft || $this->lines === []) {
            throw new DomainException('Only a complete Draft purchase credit can be finalized.');
        }$this->status = PurchaseCreditInvoiceStatus::Finalized;
        $this->finalizedBy = $actor;
        $this->finalizedAt = $at;
    }

    /** Reconstitution compatibility only; PC-001 exposes no post use case. */
    public function post(): void
    {
        if ($this->status === PurchaseCreditInvoiceStatus::Posted) {
            return;
        }
        if ($this->status !== PurchaseCreditInvoiceStatus::Finalized) {
            throw new DomainException('Only Finalized can be posted.');
        }$this->status = PurchaseCreditInvoiceStatus::Posted;
    }

    public function cancel(?UserId $actor = null, ?DateTimeImmutable $at = null): void
    {
        if ($this->status === PurchaseCreditInvoiceStatus::Cancelled) {
            return;
        }
        if (! in_array($this->status, [PurchaseCreditInvoiceStatus::Draft, PurchaseCreditInvoiceStatus::Finalized], true)) {
            throw new DomainException('Purchase credit cannot be cancelled from this state.');
        }$this->status = PurchaseCreditInvoiceStatus::Cancelled;
        $this->cancelledBy = $actor;
        $this->cancelledAt = $at;
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
        $sum = new Money('0', $this->currency);
        foreach ($this->lines as $line) {
            $sum = $sum->add($line->{$method}());
        }

        return $sum;
    }

    private function assertDraft(): void
    {
        if ($this->status !== PurchaseCreditInvoiceStatus::Draft) {
            throw new DomainException('Purchase credit is immutable outside Draft.');
        }
    }

    private function addReconstitutedLine(PurchaseCreditInvoiceLine $line): void
    {
        $key = $line->id()->toString();
        if (isset($this->lines[$key])) {
            throw new DomainException('Duplicate purchase credit line identity.');
        }$source = $line->sourcePurchaseInvoiceLineId()?->toString();
        foreach ($this->lines as $existing) {
            if ($source !== null && $existing->sourcePurchaseInvoiceLineId()?->toString() === $source) {
                throw new DomainException('A source line can only be selected once.');
            }
        }$this->lines[$key] = $line;
    }
}

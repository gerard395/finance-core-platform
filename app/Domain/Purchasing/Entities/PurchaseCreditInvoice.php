<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;

final class PurchaseCreditInvoice
{
    /** @var array<string, PurchaseCreditInvoiceLine> */
    private array $lines = [];

    public function __construct(
        private readonly PurchaseCreditInvoiceId $id,
        private readonly PurchaseCreditInvoiceNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly SupplierId $supplierId,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $creditInvoiceDate,
        private readonly ?PurchaseInvoiceId $sourcePurchaseInvoiceId,
        private PurchaseCreditInvoiceStatus $status,
    ) {}

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

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function creditInvoiceDate(): DateTimeImmutable
    {
        return $this->creditInvoiceDate;
    }

    public function sourcePurchaseInvoiceId(): ?PurchaseInvoiceId
    {
        return $this->sourcePurchaseInvoiceId;
    }

    public function status(): PurchaseCreditInvoiceStatus
    {
        return $this->status;
    }

    /** @return list<PurchaseCreditInvoiceLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function line(PurchaseCreditInvoiceLineId $lineId): ?PurchaseCreditInvoiceLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function hasLine(PurchaseCreditInvoiceLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function addLine(PurchaseCreditInvoiceLine $line): void
    {
        $this->assertDraftForLineChanges();
        $key = $line->id()->toString();

        if (isset($this->lines[$key])) {
            throw new DomainException('Purchase credit invoice already contains a line with this identity.');
        }

        $this->lines[$key] = $line;
    }

    public function removeLine(PurchaseCreditInvoiceLineId $lineId): void
    {
        $this->assertDraftForLineChanges();
        unset($this->lines[$lineId->toString()]);
    }

    public function finalize(): void
    {
        if ($this->status === PurchaseCreditInvoiceStatus::Draft && $this->lines === []) {
            throw new DomainException('Purchase credit invoice must contain at least one line before it can be finalized.');
        }

        $this->transitionTo(PurchaseCreditInvoiceStatus::Finalized, [PurchaseCreditInvoiceStatus::Draft]);
    }

    public function post(): void
    {
        $this->transitionTo(PurchaseCreditInvoiceStatus::Posted, [PurchaseCreditInvoiceStatus::Finalized]);
    }

    public function cancel(): void
    {
        $this->transitionTo(PurchaseCreditInvoiceStatus::Cancelled, [
            PurchaseCreditInvoiceStatus::Draft,
            PurchaseCreditInvoiceStatus::Finalized,
        ]);
    }

    /** @param list<PurchaseCreditInvoiceStatus> $allowedFrom */
    private function transitionTo(PurchaseCreditInvoiceStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Purchase credit invoice cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }

    private function assertDraftForLineChanges(): void
    {
        if ($this->status !== PurchaseCreditInvoiceStatus::Draft) {
            throw new DomainException('Purchase credit invoice lines can only be changed while the purchase credit invoice is in draft.');
        }
    }
}

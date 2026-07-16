<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;
use DomainException;

final class PurchaseCreditInvoice
{
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

    public function finalize(): void
    {
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
}

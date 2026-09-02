<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInternationalTaxSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class PurchaseCreditInvoiceLine
{
    private Money $net;

    private Money $taxAmount;

    private Money $gross;

    public function __construct(private PurchaseCreditInvoiceLineId $id, private LineDescription $description, private Quantity $quantity, private Money $unitPrice, private ?PurchaseInvoiceLineId $sourcePurchaseInvoiceLineId = null, private ?PurchaseAccountSnapshot $account = null, private ?PurchaseTaxSnapshot $tax = null, ?Money $net = null, ?Money $taxAmount = null, ?Money $gross = null, private ?TaxPostingId $sourceTaxPostingId = null, private ?PurchaseCreditInternationalTaxSnapshot $internationalTaxSnapshot = null)
    {
        if ($unitPrice->isNegative()) {
            throw new InvalidArgumentException('Unit price cannot be negative.');
        }
        $this->net = $net ?? $unitPrice->multiply($quantity->value());
        $this->taxAmount = $taxAmount ?? new Money('0', $unitPrice->currency());
        $this->gross = $gross ?? $this->net->add($this->taxAmount);
        if (! $this->net->equals($unitPrice->multiply($quantity->value())) || ! $this->gross->equals($this->net->add($this->taxAmount))) {
            throw new InvalidArgumentException('Purchase credit line must exactly copy its source amounts.');
        }
    }

    public function id(): PurchaseCreditInvoiceLineId
    {
        return $this->id;
    }

    public function description(): LineDescription
    {
        return $this->description;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function lineTotal(): Money
    {
        return $this->net;
    }

    public function net(): Money
    {
        return $this->net;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function gross(): Money
    {
        return $this->gross;
    }

    public function sourcePurchaseInvoiceLineId(): ?PurchaseInvoiceLineId
    {
        return $this->sourcePurchaseInvoiceLineId;
    }

    public function account(): ?PurchaseAccountSnapshot
    {
        return $this->account;
    }

    public function tax(): ?PurchaseTaxSnapshot
    {
        return $this->tax;
    }

    public function sourceTaxPostingId(): ?TaxPostingId
    {
        return $this->sourceTaxPostingId;
    }

    public function internationalTaxSnapshot(): ?PurchaseCreditInternationalTaxSnapshot
    {
        return $this->internationalTaxSnapshot;
    }

    /** @return list<TaxPostingId> */
    public function sourceTaxPostingIds(): array
    {
        return $this->internationalTaxSnapshot?->originalTaxPostingIds
            ?? ($this->sourceTaxPostingId === null ? [] : [$this->sourceTaxPostingId]);
    }
}

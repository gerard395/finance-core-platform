<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Purchasing\ValueObjects\InternationalPurchaseSourceFacts;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxTreatmentSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class PurchaseInvoiceLine
{
    public function __construct(private PurchaseInvoiceLineId $id, private LineDescription $description, private Quantity $quantity, private Money $unitPrice, private PurchaseAccountSnapshot $account, private PurchaseTaxSnapshot $tax, private Money $net, private Money $taxAmount, private Money $gross, private ?DeductibilityBasisPoints $deductibility = null, private ?InternationalPurchaseSourceFacts $internationalSourceFacts = null, private ?PurchaseTaxTreatmentSnapshot $treatmentSnapshot = null)
    {
        if (($internationalSourceFacts !== null) !== $tax->internationalDefinitionAuthoritative) {
            throw new InvalidArgumentException('International purchase source facts and definition-authoritative selector must be present together.');
        }
        if ($treatmentSnapshot !== null && $internationalSourceFacts === null) {
            throw new InvalidArgumentException('An international treatment snapshot requires international source facts.');
        }
        if ($unitPrice->isNegative() || ! $net->equals($unitPrice->multiply($quantity->value())) || ! $gross->equals($net->add($taxAmount))) {
            throw new InvalidArgumentException('Purchase invoice line amounts are inconsistent.');
        }
        foreach ([$unitPrice, $net, $taxAmount, $gross] as $money) {
            if (! $money->currency()->equals($unitPrice->currency())) {
                throw new InvalidArgumentException('Purchase invoice line amounts must use one currency.');
            }
        }
    }

    public function id(): PurchaseInvoiceLineId
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

    public function account(): PurchaseAccountSnapshot
    {
        return $this->account;
    }

    public function tax(): PurchaseTaxSnapshot
    {
        return $this->tax;
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

    public function lineTotal(): Money
    {
        return $this->net;
    }

    public function deductibility(): ?DeductibilityBasisPoints
    {
        return $this->deductibility;
    }

    public function internationalSourceFacts(): ?InternationalPurchaseSourceFacts
    {
        return $this->internationalSourceFacts;
    }

    public function treatmentSnapshot(): ?PurchaseTaxTreatmentSnapshot
    {
        return $this->treatmentSnapshot;
    }

    public function withTreatmentSnapshot(PurchaseTaxTreatmentSnapshot $snapshot): self
    {
        return new self($this->id, $this->description, $this->quantity, $this->unitPrice, $this->account, $this->tax, $this->net, $this->taxAmount, $this->gross, $this->deductibility, $snapshot->sourceFacts, $snapshot);
    }
}

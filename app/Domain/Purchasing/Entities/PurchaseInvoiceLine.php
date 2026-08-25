<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Entities;

use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class PurchaseInvoiceLine
{
    public function __construct(private PurchaseInvoiceLineId $id, private LineDescription $description, private Quantity $quantity, private Money $unitPrice, private PurchaseAccountSnapshot $account, private PurchaseTaxSnapshot $tax, private Money $net, private Money $taxAmount, private Money $gross)
    {
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
}

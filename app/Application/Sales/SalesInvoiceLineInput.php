<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;

final readonly class SalesInvoiceLineInput
{
    public function __construct(private SalesInvoiceLineId $id, private LineDescription $description, private Quantity $quantity, private Money $unitPrice, private TaxCodeId $taxCodeId) {}

    public function id(): SalesInvoiceLineId
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

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }
}

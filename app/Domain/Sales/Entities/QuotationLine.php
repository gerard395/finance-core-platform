<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Sales\ValueObjects\LineDescription;
use App\Domain\Sales\ValueObjects\Quantity;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class QuotationLine
{
    private Money $lineTotal;

    public function __construct(
        private QuotationLineId $id,
        private LineDescription $description,
        private Quantity $quantity,
        private Money $unitPrice,
    ) {
        if (str_starts_with($unitPrice->amount(), '-')) {
            throw new InvalidArgumentException('Unit price cannot be negative.');
        }

        $this->lineTotal = $this->unitPrice->multiply($this->quantity->value());
    }

    public function id(): QuotationLineId
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
        return $this->lineTotal;
    }
}

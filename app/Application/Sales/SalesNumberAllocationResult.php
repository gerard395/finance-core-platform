<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use InvalidArgumentException;

final readonly class SalesNumberAllocationResult
{
    private function __construct(
        private SalesNumberAllocationStatus $status,
        private SalesNumberType $type,
        private QuotationNumber|OrderNumber|SalesInvoiceNumber|SalesCreditInvoiceNumber|null $number,
    ) {
        if (($status === SalesNumberAllocationStatus::Success) !== ($number !== null)) {
            throw new InvalidArgumentException('A successful Sales number allocation requires exactly one number.');
        }

        if ($number !== null && ! self::matches($type, $number)) {
            throw new InvalidArgumentException('Sales number type and allocated value must match.');
        }
    }

    public static function success(
        SalesNumberType $type,
        QuotationNumber|OrderNumber|SalesInvoiceNumber|SalesCreditInvoiceNumber $number,
    ): self {
        return new self(SalesNumberAllocationStatus::Success, $type, $number);
    }

    public static function sequenceMissing(SalesNumberType $type): self
    {
        return new self(SalesNumberAllocationStatus::SequenceMissing, $type, null);
    }

    public static function sequenceInactive(SalesNumberType $type): self
    {
        return new self(SalesNumberAllocationStatus::SequenceInactive, $type, null);
    }

    public function status(): SalesNumberAllocationStatus
    {
        return $this->status;
    }

    public function type(): SalesNumberType
    {
        return $this->type;
    }

    public function number(): QuotationNumber|OrderNumber|SalesInvoiceNumber|SalesCreditInvoiceNumber|null
    {
        return $this->number;
    }

    private static function matches(
        SalesNumberType $type,
        QuotationNumber|OrderNumber|SalesInvoiceNumber|SalesCreditInvoiceNumber $number,
    ): bool {
        return match ($type) {
            SalesNumberType::Quotation => $number instanceof QuotationNumber,
            SalesNumberType::Order => $number instanceof OrderNumber,
            SalesNumberType::SalesInvoice => $number instanceof SalesInvoiceNumber,
            SalesNumberType::SalesCreditInvoice => $number instanceof SalesCreditInvoiceNumber,
        };
    }
}

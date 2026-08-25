<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class SalesDocumentSource
{
    private function __construct(public SalesDocumentType $type, public string $id) {}

    public static function quotation(QuotationId $id): self
    {
        return new self(SalesDocumentType::Quotation, $id->toString());
    }

    public static function invoice(SalesInvoiceId $id): self
    {
        return new self(SalesDocumentType::SalesInvoice, $id->toString());
    }

    public static function creditInvoice(SalesCreditInvoiceId $id): self
    {
        return new self(SalesDocumentType::SalesCreditInvoice, $id->toString());
    }
}

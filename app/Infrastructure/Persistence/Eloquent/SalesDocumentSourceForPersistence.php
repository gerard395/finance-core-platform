<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\SalesDocumentSource;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;

final readonly class SalesDocumentSourceForPersistence
{
    public function __construct(private SalesDocumentType $type, private string $id) {}

    public function toPublicSource(): SalesDocumentSource
    {
        $uuid = new Uuid($this->id);

        return match ($this->type) {
            SalesDocumentType::Quotation => SalesDocumentSource::quotation(new QuotationId($uuid)), SalesDocumentType::SalesInvoice => SalesDocumentSource::invoice(new SalesInvoiceId($uuid)), SalesDocumentType::SalesCreditInvoice => SalesDocumentSource::creditInvoice(new SalesCreditInvoiceId($uuid))
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;

final readonly class SalesCreditInvoiceDetail
{
    public function __construct(public SalesCreditInvoice $invoice, public SalesInvoiceNumber $sourceInvoiceNumber) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum SalesDocumentType: string
{
    case Quotation = 'quotation';
    case SalesInvoice = 'sales_invoice';
    case SalesCreditInvoice = 'sales_credit_invoice';
}

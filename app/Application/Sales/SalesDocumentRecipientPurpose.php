<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesDocumentRecipientPurpose: string
{
    case Quotation = 'quotation';
    case SalesInvoice = 'sales_invoice';
    case SalesCreditInvoice = 'sales_credit_invoice';
}

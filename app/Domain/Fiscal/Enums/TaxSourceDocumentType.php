<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxSourceDocumentType: string
{
    case SalesInvoice = 'sales_invoice';
    case PurchaseInvoice = 'purchase_invoice';
}

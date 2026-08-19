<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxSourceDocumentType: string
{
    case SalesInvoice = 'sales_invoice';
    case PurchaseInvoice = 'purchase_invoice';
    case SalesCreditInvoice = 'sales_credit_invoice';
    case PurchaseCreditInvoice = 'purchase_credit_invoice';
}

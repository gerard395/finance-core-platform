<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum DocumentType: string
{
    case Quotation = 'quotation';
    case Order = 'order';
    case SalesInvoice = 'sales_invoice';
    case SalesCreditInvoice = 'sales_credit_invoice';
    case PurchaseInvoice = 'purchase_invoice';
    case PurchaseCreditInvoice = 'purchase_credit_invoice';
    case Bank = 'bank';
    case Journal = 'journal';
}

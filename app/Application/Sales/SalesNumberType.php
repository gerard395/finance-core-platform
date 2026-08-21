<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;

enum SalesNumberType: string
{
    case Quotation = 'quotation';
    case Order = 'order';
    case SalesInvoice = 'sales_invoice';
    case SalesCreditInvoice = 'sales_credit_invoice';

    public function number(int $value): QuotationNumber|OrderNumber|SalesInvoiceNumber|SalesCreditInvoiceNumber
    {
        $formatted = match ($this) {
            self::Quotation => sprintf('Q%06d', $value),
            self::Order => sprintf('O%06d', $value),
            self::SalesInvoice => sprintf('F%06d', $value),
            self::SalesCreditInvoice => sprintf('C%06d', $value),
        };

        return match ($this) {
            self::Quotation => new QuotationNumber($formatted),
            self::Order => new OrderNumber($formatted),
            self::SalesInvoice => new SalesInvoiceNumber($formatted),
            self::SalesCreditInvoice => new SalesCreditInvoiceNumber($formatted),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesCreditInvoiceSortField
{
    case Number;
    case CustomerName;
    case CreditDate;
    case Status;
}

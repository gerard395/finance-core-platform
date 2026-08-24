<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoiceSortField
{
    case Number;
    case CustomerName;
    case InvoiceDate;
    case DueDate;
    case Status;
}

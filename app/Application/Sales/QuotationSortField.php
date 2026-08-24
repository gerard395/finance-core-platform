<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum QuotationSortField
{
    case Number;
    case CustomerName;
    case QuotationDate;
    case ExpiryDate;
    case Status;
}

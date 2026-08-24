<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum OrderSortField
{
    case Number;
    case CustomerName;
    case OrderDate;
    case SourceQuotation;
    case Status;
}

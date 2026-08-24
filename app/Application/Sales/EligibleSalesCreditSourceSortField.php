<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum EligibleSalesCreditSourceSortField
{
    case InvoiceDate;
    case Number;
    case CustomerName;
}

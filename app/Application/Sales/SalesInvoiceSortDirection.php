<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoiceSortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

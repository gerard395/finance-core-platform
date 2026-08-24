<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum QuotationSortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

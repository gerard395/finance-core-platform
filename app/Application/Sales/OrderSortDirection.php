<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum OrderSortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

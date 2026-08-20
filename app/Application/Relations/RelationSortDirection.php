<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationSortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

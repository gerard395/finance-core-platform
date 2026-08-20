<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationSortField: string
{
    case DisplayName = 'display_name';
    case Code = 'code';
    case Status = 'status';
}

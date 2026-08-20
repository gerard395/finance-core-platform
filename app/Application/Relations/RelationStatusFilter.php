<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationStatusFilter: string
{
    case All = 'all';
    case Active = 'active';
    case Inactive = 'inactive';
}

<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationClassificationFilter: string
{
    case All = 'all';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Both = 'both';
    case Neither = 'neither';
}

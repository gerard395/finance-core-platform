<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationNumberType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function format(int $value): string
    {
        return match ($this) {
            self::Customer => sprintf('C%06d', $value),
            self::Supplier => sprintf('S%06d', $value),
        };
    }
}

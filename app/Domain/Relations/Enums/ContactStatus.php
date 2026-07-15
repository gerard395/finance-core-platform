<?php

declare(strict_types=1);

namespace App\Domain\Relations\Enums;

enum ContactStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum RoleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

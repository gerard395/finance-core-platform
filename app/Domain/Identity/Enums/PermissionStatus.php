<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum PermissionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

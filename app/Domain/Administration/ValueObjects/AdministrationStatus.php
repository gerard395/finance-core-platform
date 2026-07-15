<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

enum AdministrationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum AdministrationBankAccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

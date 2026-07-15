<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

enum AccountingNumberFormat: string
{
    case Dutch = 'nl';
    case English = 'en';
}

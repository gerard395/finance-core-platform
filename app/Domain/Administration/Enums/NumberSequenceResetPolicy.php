<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum NumberSequenceResetPolicy: string
{
    case Never = 'never';
    case FiscalYear = 'fiscal_year';
}

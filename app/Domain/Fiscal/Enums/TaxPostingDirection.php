<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxPostingDirection: string
{
    case Input = 'input';
    case Output = 'output';
}

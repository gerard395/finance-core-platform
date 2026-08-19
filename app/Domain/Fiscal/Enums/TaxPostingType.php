<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum TaxPostingType: string
{
    case Original = 'original';
    case Reversal = 'reversal';
}

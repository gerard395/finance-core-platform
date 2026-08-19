<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum OpenItemSettlementType: string
{
    case Applied = 'applied';
    case Reversal = 'reversal';
}

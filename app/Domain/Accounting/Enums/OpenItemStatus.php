<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum OpenItemStatus: string
{
    case Open = 'open';
    case PartiallySettled = 'partially_settled';
    case Closed = 'closed';
}

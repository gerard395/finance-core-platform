<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum OpenItemSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}

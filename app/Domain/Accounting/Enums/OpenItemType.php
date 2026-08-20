<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum OpenItemType: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
}

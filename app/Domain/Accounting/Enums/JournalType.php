<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum JournalType: string
{
    case Sales = 'sales';
    case Purchase = 'purchase';
    case Bank = 'bank';
    case Cash = 'cash';
    case General = 'general';
}

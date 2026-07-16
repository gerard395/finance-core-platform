<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}

<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankEntryManualAction: string
{
    case Ignore = 'ignored';
    case RestoreFromIgnored = 'restored_from_ignored';
}

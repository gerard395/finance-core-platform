<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankEntryDerivedState: string
{
    case Unresolved = 'unresolved';
    case Ignored = 'ignored';
    case Reconciled = 'reconciled';
    case Reversed = 'reversed';
}

<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankEntryDirection: string
{
    case Credit = 'CRDT';
    case Debit = 'DBIT';
}

<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankTransactionStatus: string
{
    case Imported = 'imported';
    case Matched = 'matched';
    case Posted = 'posted';
}

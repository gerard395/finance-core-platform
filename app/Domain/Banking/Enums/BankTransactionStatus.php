<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankTransactionStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}

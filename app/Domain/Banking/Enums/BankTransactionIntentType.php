<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankTransactionIntentType: string
{
    case Payment = 'payment';
    case Other = 'other';
}

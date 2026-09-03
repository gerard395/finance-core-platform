<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankEntrySuggestionConfidence: int
{
    case Exact = 10000;
    case High = 8500;
    case Medium = 6500;
    case Low = 3500;
}

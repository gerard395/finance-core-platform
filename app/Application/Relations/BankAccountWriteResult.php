<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum BankAccountWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum AdministrationBankAccountWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
    case DuplicateIban;
}

<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum AddressWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
}

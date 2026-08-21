<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum ContactWriteResult
{
    case Success;
    case NotFound;
    case DuplicateIdentity;
}

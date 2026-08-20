<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationWriteResult
{
    case Success;
    case DuplicateIdentity;
    case DuplicateCode;
    case NotFound;
}

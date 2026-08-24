<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum MatchOpenItemsStatus
{
    case Success;
    case NotFound;
    case InvalidMatch;
    case AlreadyExists;
    case NothingToMatch;
    case PersistenceFailure;
}

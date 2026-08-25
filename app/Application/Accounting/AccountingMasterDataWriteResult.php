<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingMasterDataWriteResult
{
    case Success;
    case DuplicateCode;
    case InvalidInput;
    case NotFound;
    case PersistenceConflict;
}

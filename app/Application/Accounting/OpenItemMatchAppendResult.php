<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum OpenItemMatchAppendResult
{
    case Appended;
    case AlreadyExists;
}

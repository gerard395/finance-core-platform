<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesNumberAllocationStatus
{
    case Success;
    case SequenceMissing;
    case SequenceInactive;
}

<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationNumberAllocationStatus
{
    case Success;
    case SequenceMissing;
    case SequenceInactive;
}

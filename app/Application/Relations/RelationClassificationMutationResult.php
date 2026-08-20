<?php

declare(strict_types=1);

namespace App\Application\Relations;

enum RelationClassificationMutationResult
{
    case Success;
    case NotFound;
    case NoClassification;
    case SequenceMissing;
    case SequenceInactive;
    case PersistenceConflict;
}

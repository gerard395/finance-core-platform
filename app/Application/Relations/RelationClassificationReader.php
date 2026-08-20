<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

interface RelationClassificationReader
{
    public function classify(AdministrationId $administrationId, RelationId $relationId): RelationClassification;
}

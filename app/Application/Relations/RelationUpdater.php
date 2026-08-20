<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;

interface RelationUpdater
{
    public function update(AdministrationId $administrationId, Relation $relation): RelationWriteResult;
}

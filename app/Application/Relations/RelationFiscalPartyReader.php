<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

interface RelationFiscalPartyReader
{
    public function findFiscalParty(AdministrationId $administrationId, RelationId $relationId): ?RelationFiscalParty;
}

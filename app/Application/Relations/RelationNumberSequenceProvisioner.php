<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface RelationNumberSequenceProvisioner
{
    public function ensureForAdministration(AdministrationId $administrationId): void;
}

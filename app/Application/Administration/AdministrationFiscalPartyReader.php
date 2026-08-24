<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface AdministrationFiscalPartyReader
{
    public function findFiscalParty(AdministrationId $administrationId): ?AdministrationFiscalParty;
}

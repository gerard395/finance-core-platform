<?php

declare(strict_types=1);

namespace App\Application\Development;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface DevelopmentAccountingMasterDataProvisioner
{
    public function provision(AdministrationId $administrationId): DevelopmentAccountingMasterDataProvisioningResult;
}

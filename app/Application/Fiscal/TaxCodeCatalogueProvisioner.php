<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface TaxCodeCatalogueProvisioner
{
    /**
     * Creates missing Dutch basic Output TaxCodes without changing existing codes.
     *
     * @throws TaxCodeCatalogueProvisioningConflict
     */
    public function ensureDutchBasicOutputForAdministration(AdministrationId $administrationId): void;

    /**
     * Creates missing fully deductible domestic Input TaxCodes without changing existing codes.
     *
     * @throws TaxCodeCatalogueProvisioningConflict
     */
    public function ensureDutchBasicInputForAdministration(AdministrationId $administrationId): void;
}

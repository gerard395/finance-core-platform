<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class UpdateAdministrationSettings
{
    public function __construct(
        private AdministrationRepository $administrations,
        private AdministrationSettingsUpdater $settings,
        private TransactionManager $transactions,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        AdministrationName $name,
        ?string $description,
        ?VatIdentificationNumber $vatIdentificationNumber = null,
        ?CountryCode $fiscalJurisdiction = null,
        bool $updateFiscalMasterData = false,
    ): AdministrationSettingsWriteResult {
        return $this->transactions->run(function () use ($administrationId, $name, $description, $vatIdentificationNumber, $fiscalJurisdiction, $updateFiscalMasterData): AdministrationSettingsWriteResult {
            $administration = $this->administrations->findById($administrationId);
            if ($administration === null) {
                return AdministrationSettingsWriteResult::NotFound;
            }

            $administration->rename($name);
            $administration->changeDescription($description);
            if ($updateFiscalMasterData || $vatIdentificationNumber !== null || $fiscalJurisdiction !== null) {
                $administration->changeFiscalMasterData($vatIdentificationNumber, $fiscalJurisdiction);
            }

            return $this->settings->updateSettings($administration)
                ? AdministrationSettingsWriteResult::Success
                : AdministrationSettingsWriteResult::NotFound;
        });
    }
}

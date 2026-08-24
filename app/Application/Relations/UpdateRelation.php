<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class UpdateRelation
{
    public function __construct(
        private RelationReadRepository $relationReader,
        private RelationUpdater $relationUpdater,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        RelationId $relationId,
        DisplayName $displayName,
        bool $active,
        ?VatIdentificationNumber $vatIdentificationNumber = null,
        ?CountryCode $fiscalJurisdiction = null,
        bool $updateFiscalMasterData = false,
    ): RelationWriteResult {
        $relation = $this->relationReader->findByIdForAdministration($administrationId, $relationId);

        if ($relation === null) {
            return RelationWriteResult::NotFound;
        }

        $relation->rename($displayName);
        $active ? $relation->activate() : $relation->deactivate();
        if ($updateFiscalMasterData || $vatIdentificationNumber !== null || $fiscalJurisdiction !== null) {
            $relation->changeFiscalMasterData($vatIdentificationNumber, $fiscalJurisdiction);
        }

        return $this->relationUpdater->update($administrationId, $relation);
    }
}

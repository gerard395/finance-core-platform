<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class CreateRelation
{
    public function __construct(private RelationCreator $relations) {}

    public function execute(
        AdministrationId $administrationId,
        RelationId $relationId,
        RelationCode $code,
        DisplayName $displayName,
        bool $active = true,
        ?VatIdentificationNumber $vatIdentificationNumber = null,
        ?CountryCode $fiscalJurisdiction = null,
    ): RelationWriteResult {
        return $this->relations->create(
            $administrationId,
            new Relation($relationId, $code, $displayName, $active, $vatIdentificationNumber, $fiscalJurisdiction),
        );
    }
}

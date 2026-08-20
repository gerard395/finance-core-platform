<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;

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
    ): RelationWriteResult {
        $relation = $this->relationReader->findByIdForAdministration($administrationId, $relationId);

        if ($relation === null) {
            return RelationWriteResult::NotFound;
        }

        $relation->rename($displayName);
        $active ? $relation->activate() : $relation->deactivate();

        return $this->relationUpdater->update($administrationId, $relation);
    }
}

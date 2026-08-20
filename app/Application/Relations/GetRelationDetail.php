<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class GetRelationDetail
{
    public function __construct(
        private RelationReadRepository $relations,
        private RelationClassificationReader $classifications,
    ) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId): ?RelationDetail
    {
        $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);

        if ($relation === null) {
            return null;
        }

        $classification = $this->classifications->classify($administrationId, $relationId);

        return new RelationDetail(
            $relation->id(),
            $relation->code(),
            $relation->displayName(),
            $relation->isActive(),
            $classification->isCustomer(),
            $classification->isSupplier(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\RelationClassification;
use App\Application\Relations\RelationClassificationReader;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SupplierRecord;

final class EloquentRelationClassificationReader implements RelationClassificationReader
{
    public function classify(AdministrationId $administrationId, RelationId $relationId): RelationClassification
    {
        $scope = static fn ($query) => $query
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->where('active', true);

        return new RelationClassification(
            $scope(CustomerRecord::query())->exists(),
            $scope(SupplierRecord::query())->exists(),
        );
    }
}

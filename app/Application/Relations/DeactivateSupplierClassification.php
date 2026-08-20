<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class DeactivateSupplierClassification
{
    public function __construct(
        private TransactionManager $transactions,
        private RelationReadRepository $relations,
        private SupplierReadRepository $suppliers,
        private SupplierClassificationWriter $writer,
    ) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId): RelationClassificationMutationResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $relationId): RelationClassificationMutationResult {
                if ($this->relations->findByIdForAdministration($administrationId, $relationId) === null) {
                    return RelationClassificationMutationResult::NotFound;
                }

                $supplier = $this->suppliers->findForRelation($administrationId, $relationId);
                if ($supplier === null) {
                    return RelationClassificationMutationResult::NoClassification;
                }

                $supplier->deactivate();
                $this->writer->persist($administrationId, $supplier);

                return RelationClassificationMutationResult::Success;
            });
        } catch (ClassificationPersistenceConflict) {
            return RelationClassificationMutationResult::PersistenceConflict;
        }
    }
}

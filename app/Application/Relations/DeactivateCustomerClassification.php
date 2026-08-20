<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class DeactivateCustomerClassification
{
    public function __construct(
        private TransactionManager $transactions,
        private RelationReadRepository $relations,
        private CustomerReadRepository $customers,
        private CustomerClassificationWriter $writer,
    ) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId): RelationClassificationMutationResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $relationId): RelationClassificationMutationResult {
                if ($this->relations->findByIdForAdministration($administrationId, $relationId) === null) {
                    return RelationClassificationMutationResult::NotFound;
                }

                $customer = $this->customers->findForRelation($administrationId, $relationId);
                if ($customer === null) {
                    return RelationClassificationMutationResult::NoClassification;
                }

                $customer->deactivate();
                $this->writer->persist($administrationId, $customer);

                return RelationClassificationMutationResult::Success;
            });
        } catch (ClassificationPersistenceConflict) {
            return RelationClassificationMutationResult::PersistenceConflict;
        }
    }
}

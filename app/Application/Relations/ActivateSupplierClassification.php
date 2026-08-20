<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierNumber;

final readonly class ActivateSupplierClassification
{
    public function __construct(
        private TransactionManager $transactions,
        private RelationReadRepository $relations,
        private SupplierReadRepository $suppliers,
        private SupplierClassificationWriter $writer,
        private RelationNumberAllocator $numbers,
        private RelationClassificationIdentityGenerator $identities,
    ) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId): RelationClassificationMutationResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $relationId): RelationClassificationMutationResult {
                if ($this->relations->findByIdForAdministration($administrationId, $relationId) === null) {
                    return RelationClassificationMutationResult::NotFound;
                }

                $supplier = $this->suppliers->findForRelation($administrationId, $relationId);

                if ($supplier !== null) {
                    $supplier->activate();
                    $this->writer->persist($administrationId, $supplier);

                    return RelationClassificationMutationResult::Success;
                }

                $allocation = $this->numbers->next($administrationId, RelationNumberType::Supplier);
                if ($allocation->status() !== RelationNumberAllocationStatus::Success) {
                    return match ($allocation->status()) {
                        RelationNumberAllocationStatus::SequenceMissing => RelationClassificationMutationResult::SequenceMissing,
                        RelationNumberAllocationStatus::SequenceInactive => RelationClassificationMutationResult::SequenceInactive,
                        RelationNumberAllocationStatus::Success => throw new \LogicException('Successful allocation must have been handled.'),
                    };
                }

                $number = $allocation->number();
                if (! $number instanceof SupplierNumber) {
                    throw new \LogicException('Supplier allocation must return a SupplierNumber.');
                }

                $this->writer->persist($administrationId, new Supplier(
                    $this->identities->supplierId(),
                    $relationId,
                    $number,
                    true,
                ));

                return RelationClassificationMutationResult::Success;
            });
        } catch (ClassificationPersistenceConflict) {
            return RelationClassificationMutationResult::PersistenceConflict;
        }
    }
}

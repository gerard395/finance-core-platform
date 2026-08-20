<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class ActivateCustomerClassification
{
    public function __construct(
        private TransactionManager $transactions,
        private RelationReadRepository $relations,
        private CustomerReadRepository $customers,
        private CustomerClassificationWriter $writer,
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

                $customer = $this->customers->findForRelation($administrationId, $relationId);

                if ($customer !== null) {
                    $customer->activate();
                    $this->writer->persist($administrationId, $customer);

                    return RelationClassificationMutationResult::Success;
                }

                $allocation = $this->numbers->next($administrationId, RelationNumberType::Customer);
                if ($allocation->status() !== RelationNumberAllocationStatus::Success) {
                    return match ($allocation->status()) {
                        RelationNumberAllocationStatus::SequenceMissing => RelationClassificationMutationResult::SequenceMissing,
                        RelationNumberAllocationStatus::SequenceInactive => RelationClassificationMutationResult::SequenceInactive,
                        RelationNumberAllocationStatus::Success => throw new \LogicException('Successful allocation must have been handled.'),
                    };
                }

                $number = $allocation->number();
                if (! $number instanceof CustomerNumber) {
                    throw new \LogicException('Customer allocation must return a CustomerNumber.');
                }

                $this->writer->persist($administrationId, new Customer(
                    $this->identities->customerId(),
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

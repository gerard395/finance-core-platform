<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class ActivateAddress
{
    public function __construct(private RelationReadRepository $relations, private AddressWriter $addresses, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): AddressWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $addressId): AddressWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            $address = $relation?->address($addressId);
            if ($relation === null || $address === null) {
                return AddressWriteResult::NotFound;
            }
            $address->activate();

            return $this->addresses->update($administrationId, $relation, $addressId);
        });
    }
}

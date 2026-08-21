<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class CreateAddress
{
    public function __construct(private RelationReadRepository $relations, private AddressWriter $addresses, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId, AddressType $type, AddressLine $addressLine, ?AddressLine $addressLine2, PostalCode $postalCode, City $city, CountryCode $countryCode): AddressWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $addressId, $type, $addressLine, $addressLine2, $postalCode, $city, $countryCode): AddressWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            if ($relation === null) {
                return AddressWriteResult::NotFound;
            }
            if ($relation->hasAddress($addressId)) {
                return AddressWriteResult::DuplicateIdentity;
            }
            $relation->addAddress(new Address($addressId, $type, $addressLine, $addressLine2, $postalCode, $city, $countryCode, true));

            return $this->addresses->create($administrationId, $relation, $addressId);
        });
    }
}

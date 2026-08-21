<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\AddressWriter;
use App\Application\Relations\AddressWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use Illuminate\Database\QueryException;

final class EloquentAddressWriter implements AddressWriter
{
    public function create(AdministrationId $administrationId, Relation $relation, AddressId $addressId): AddressWriteResult
    {
        $address = $this->validatedAddress($administrationId, $relation, $addressId);
        if ($address === null) {
            return AddressWriteResult::NotFound;
        }
        try {
            RelationAddressRecord::query()->create($this->attributes($administrationId, $relation, $address));
        } catch (QueryException $exception) {
            if (RelationAddressRecord::query()->whereKey($addressId->toString())->exists()) {
                return AddressWriteResult::DuplicateIdentity;
            }
            throw $exception;
        }

        return AddressWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, Relation $relation, AddressId $addressId): AddressWriteResult
    {
        $address = $this->validatedAddress($administrationId, $relation, $addressId);
        if ($address === null) {
            return AddressWriteResult::NotFound;
        }
        $record = RelationAddressRecord::query()->whereKey($addressId->toString())->where('administration_id', $administrationId->toString())->where('relation_id', $relation->id()->toString())->first();
        if ($record === null) {
            return AddressWriteResult::NotFound;
        }
        $record->fill($this->attributes($administrationId, $relation, $address));
        $record->save();

        return AddressWriteResult::Success;
    }

    private function validatedAddress(AdministrationId $administrationId, Relation $relation, AddressId $addressId): ?Address
    {
        if (! RelationRecord::query()->whereKey($relation->id()->toString())->where('administration_id', $administrationId->toString())->exists()) {
            return null;
        }

        return $relation->address($addressId);
    }

    /** @return array<string, string|bool|null> */
    private function attributes(AdministrationId $administrationId, Relation $relation, Address $address): array
    {
        return ['address_id' => $address->id()->toString(), 'administration_id' => $administrationId->toString(), 'relation_id' => $relation->id()->toString(),
            'address_type' => $address->type()->value, 'address_line_1' => $address->addressLine()->value(), 'address_line_2' => $address->addressLine2()?->value(),
            'postal_code' => $address->postalCode()->value(), 'city' => $address->city()->value(), 'country_code' => $address->countryCode()->value(), 'active' => $address->isActive()];
    }
}

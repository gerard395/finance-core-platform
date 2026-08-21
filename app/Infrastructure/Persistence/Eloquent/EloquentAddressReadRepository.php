<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\AddressDetail;
use App\Application\Relations\AddressListItem;
use App\Application\Relations\AddressReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;

final class EloquentAddressReadRepository implements AddressReadRepository
{
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array
    {
        return RelationAddressRecord::query()->where('administration_id', $administrationId->toString())->where('relation_id', $relationId->toString())
            ->orderBy('address_type')->orderBy('address_id')->get()
            ->map(fn (RelationAddressRecord $record): AddressListItem => $this->project($record, AddressListItem::class))->all();
    }

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): ?AddressDetail
    {
        $record = RelationAddressRecord::query()->whereKey($addressId->toString())->where('administration_id', $administrationId->toString())->where('relation_id', $relationId->toString())->first();

        return $record === null ? null : $this->project($record, AddressDetail::class);
    }

    /** @template T of AddressListItem
     * @param  class-string<T>  $class
     * @return T
     */
    private function project(RelationAddressRecord $record, string $class): AddressListItem
    {
        $line2 = $record->getAttribute('address_line_2');

        return new $class(
            new AddressId(new Uuid($record->getAttribute('address_id'))), AddressType::from($record->getAttribute('address_type')),
            new AddressLine($record->getAttribute('address_line_1')), $line2 === null ? null : new AddressLine($line2),
            new PostalCode($record->getAttribute('postal_code')), new City($record->getAttribute('city')),
            new CountryCode($record->getAttribute('country_code')), (bool) $record->getAttribute('active'),
        );
    }
}

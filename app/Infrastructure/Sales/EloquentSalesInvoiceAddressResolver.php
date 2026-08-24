<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesInvoiceAddressResolver;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;

final class EloquentSalesInvoiceAddressResolver implements SalesInvoiceAddressResolver
{
    public function resolve(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): ?SalesAddressSnapshot
    {
        $record = RelationAddressRecord::query()->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())->where('address_id', $addressId->toString())
            ->where('address_type', AddressType::Invoice->value)->where('active', true)->first();
        if ($record === null) {
            return null;
        }
        $line2 = $record->getAttribute('address_line_2');

        return new SalesAddressSnapshot($addressId, AddressType::Invoice, new AddressLine($record->getAttribute('address_line_1')), $line2 === null ? null : new AddressLine($line2), new PostalCode($record->getAttribute('postal_code')), new City($record->getAttribute('city')), new CountryCode($record->getAttribute('country_code')));
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\QuotationAddressResolution;
use App\Application\Sales\QuotationAddressResolutionStatus;
use App\Application\Sales\QuotationAddressResolver;
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

final class EloquentQuotationAddressResolver implements QuotationAddressResolver
{
    public function resolve(AdministrationId $administrationId, RelationId $relationId, AddressId $addressId): QuotationAddressResolution
    {
        $record = RelationAddressRecord::query()
            ->whereKey($addressId->toString())
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->lockForUpdate()
            ->first();
        if ($record === null) {
            return new QuotationAddressResolution(QuotationAddressResolutionStatus::NotFound);
        }
        if (! (bool) $record->getAttribute('active')) {
            return new QuotationAddressResolution(QuotationAddressResolutionStatus::Inactive);
        }
        if ($record->getAttribute('address_type') !== AddressType::Quotation->value) {
            return new QuotationAddressResolution(QuotationAddressResolutionStatus::InvalidPurpose);
        }
        $line2 = $record->getAttribute('address_line_2');

        return new QuotationAddressResolution(QuotationAddressResolutionStatus::Success, new SalesAddressSnapshot(
            $addressId,
            AddressType::Quotation,
            new AddressLine($record->getAttribute('address_line_1')),
            $line2 === null ? null : new AddressLine($line2),
            new PostalCode($record->getAttribute('postal_code')),
            new City($record->getAttribute('city')),
            new CountryCode($record->getAttribute('country_code')),
        ));
    }
}

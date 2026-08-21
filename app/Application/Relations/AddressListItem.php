<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;

readonly class AddressListItem
{
    public function __construct(
        public AddressId $id,
        public AddressType $type,
        public AddressLine $addressLine,
        public ?AddressLine $addressLine2,
        public PostalCode $postalCode,
        public City $city,
        public CountryCode $countryCode,
        public bool $active,
    ) {}
}

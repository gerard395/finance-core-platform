<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;

final readonly class SalesAddressSnapshot
{
    public function __construct(
        private AddressId $addressId,
        private AddressType $type,
        private AddressLine $addressLine,
        private ?AddressLine $addressLine2,
        private PostalCode $postalCode,
        private City $city,
        private CountryCode $countryCode,
    ) {}

    public function addressId(): AddressId
    {
        return $this->addressId;
    }

    public function type(): AddressType
    {
        return $this->type;
    }

    public function addressLine(): AddressLine
    {
        return $this->addressLine;
    }

    public function addressLine2(): ?AddressLine
    {
        return $this->addressLine2;
    }

    public function postalCode(): PostalCode
    {
        return $this->postalCode;
    }

    public function city(): City
    {
        return $this->city;
    }

    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }
}

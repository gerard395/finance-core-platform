<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;

final class Address
{
    public function __construct(
        private readonly AddressId $id,
        private readonly AddressType $type,
        private AddressLine $addressLine,
        private ?AddressLine $addressLine2,
        private PostalCode $postalCode,
        private City $city,
        private CountryCode $countryCode,
        private bool $active,
    ) {}

    public function id(): AddressId
    {
        return $this->id;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function changeDetails(
        AddressLine $addressLine,
        ?AddressLine $addressLine2,
        PostalCode $postalCode,
        City $city,
        CountryCode $countryCode,
    ): void {
        $this->addressLine = $addressLine;
        $this->addressLine2 = $addressLine2;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}

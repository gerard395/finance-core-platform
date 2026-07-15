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
        private readonly AddressLine $addressLine,
        private readonly ?AddressLine $addressLine2,
        private readonly PostalCode $postalCode,
        private readonly City $city,
        private readonly CountryCode $countryCode,
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

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}

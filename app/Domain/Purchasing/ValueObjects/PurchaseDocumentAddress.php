<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;

final readonly class PurchaseDocumentAddress
{
    public function __construct(public AddressLine $line1, public ?AddressLine $line2, public PostalCode $postalCode, public City $city, public CountryCode $countryCode) {}
}

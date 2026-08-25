<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use InvalidArgumentException;

final readonly class SalesDocumentMasterData
{
    public function __construct(
        public ?string $displayName,
        public ?string $legalName,
        public ?string $registrationNumber,
        public ?AddressLine $addressLine1,
        public ?AddressLine $addressLine2,
        public ?PostalCode $postalCode,
        public ?City $city,
        public ?CountryCode $countryCode,
        public ?EmailAddress $businessEmail,
        public ?string $businessPhone,
        public ?string $website,
        public ?Iban $iban,
        public ?Bic $bic,
        public ?string $accountHolder,
        public ?string $senderName,
        public ?EmailAddress $senderEmail,
        public ?EmailAddress $replyTo,
    ) {
        if ($displayName === null && ($legalName !== null || $registrationNumber !== null || $iban !== null || $bic !== null)) {
            throw new InvalidArgumentException('Organisation display name is required for organisation and payment master data.');
        }
        foreach ([$displayName, $legalName, $registrationNumber, $businessPhone, $website, $accountHolder, $senderName] as $value) {
            if ($value !== null && ($value === '' || preg_match('/\A\s|\s\z|[\r\n]/u', $value) === 1 || mb_strlen($value) > 255)) {
                throw new InvalidArgumentException('Sales document master data contains an invalid text value.');
            }
        }
    }
}

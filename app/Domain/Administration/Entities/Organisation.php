<?php

declare(strict_types=1);

namespace App\Domain\Administration\Entities;

use App\Domain\Administration\ValueObjects\OrganisationId;
use InvalidArgumentException;

final class Organisation
{
    private string $displayName;

    private ?string $legalName;

    public function __construct(
        private readonly OrganisationId $id,
        string $displayName,
        ?string $legalName,
        private readonly ?string $legalForm,
        private readonly ?string $chamberOfCommerceNumber,
        private readonly ?string $vatNumber,
        private readonly ?string $primaryAddress,
        private readonly ?string $iban,
        private readonly ?string $bic,
    ) {
        self::assertValidDisplayName($displayName);
        self::assertValidOptionalValue($legalName, 'Legal name');
        self::assertValidOptionalValue($legalForm, 'Legal form');
        self::assertValidOptionalValue($chamberOfCommerceNumber, 'Chamber of Commerce number');
        self::assertValidOptionalValue($vatNumber, 'VAT number');
        self::assertValidOptionalValue($primaryAddress, 'Primary address');
        self::assertValidOptionalValue($iban, 'IBAN');
        self::assertValidOptionalValue($bic, 'BIC');

        $this->displayName = $displayName;
        $this->legalName = $legalName;
    }

    public function id(): OrganisationId
    {
        return $this->id;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function legalName(): ?string
    {
        return $this->legalName;
    }

    public function legalForm(): ?string
    {
        return $this->legalForm;
    }

    public function chamberOfCommerceNumber(): ?string
    {
        return $this->chamberOfCommerceNumber;
    }

    public function vatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function primaryAddress(): ?string
    {
        return $this->primaryAddress;
    }

    public function iban(): ?string
    {
        return $this->iban;
    }

    public function bic(): ?string
    {
        return $this->bic;
    }

    public function renameDisplayName(string $displayName): void
    {
        self::assertValidDisplayName($displayName);

        $this->displayName = $displayName;
    }

    public function changeLegalName(?string $legalName): void
    {
        self::assertValidOptionalValue($legalName, 'Legal name');

        $this->legalName = $legalName;
    }

    private static function assertValidDisplayName(string $displayName): void
    {
        if (preg_match('/\A\s|\s\z/u', $displayName) === 1) {
            throw new InvalidArgumentException('Organisation display name cannot contain leading or trailing whitespace.');
        }

        $length = mb_strlen($displayName);

        if ($length < 2 || $length > 255) {
            throw new InvalidArgumentException('Organisation display name must contain 2 to 255 characters.');
        }
    }

    private static function assertValidOptionalValue(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be empty; use null instead.', $field));
        }

        if (preg_match('/\A\s|\s\z/u', $value) === 1) {
            throw new InvalidArgumentException(sprintf('%s cannot contain leading or trailing whitespace.', $field));
        }

        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException(sprintf('%s cannot exceed 255 characters.', $field));
        }
    }
}

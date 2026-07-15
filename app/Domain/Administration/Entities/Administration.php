<?php

declare(strict_types=1);

namespace App\Domain\Administration\Entities;

use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Shared\Finance\Currency;
use DomainException;
use InvalidArgumentException;

final class Administration
{
    private AdministrationName $name;

    private ?string $description;

    private Currency $baseCurrency;

    private AdministrationStatus $status;

    private ?Organisation $organisation;

    public function __construct(
        private readonly AdministrationId $id,
        private readonly AdministrationCode $code,
        AdministrationName $name,
        ?string $description,
        Currency $baseCurrency,
        AdministrationStatus $status,
        ?Organisation $organisation = null,
    ) {
        self::assertValidDescription($description);

        $this->name = $name;
        $this->description = $description;
        $this->baseCurrency = $baseCurrency;
        $this->status = $status;
        $this->organisation = $organisation;
    }

    public function id(): AdministrationId
    {
        return $this->id;
    }

    public function code(): AdministrationCode
    {
        return $this->code;
    }

    public function name(): AdministrationName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function baseCurrency(): Currency
    {
        return $this->baseCurrency;
    }

    public function status(): AdministrationStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === AdministrationStatus::Active;
    }

    public function organisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function hasOrganisation(): bool
    {
        return $this->organisation !== null;
    }

    public function rename(AdministrationName $name): void
    {
        $this->name = $name;
    }

    public function changeDescription(?string $description): void
    {
        self::assertValidDescription($description);

        $this->description = $description;
    }

    public function changeBaseCurrency(Currency $currency): void
    {
        $this->baseCurrency = $currency;
    }

    public function activate(): void
    {
        $this->status = AdministrationStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = AdministrationStatus::Inactive;
    }

    public function attachOrganisation(Organisation $organisation): void
    {
        if ($this->organisation !== null) {
            throw new DomainException('Administration already has an Organisation.');
        }

        $this->organisation = $organisation;
    }

    public function removeOrganisation(): void
    {
        $this->organisation = null;
    }

    private static function assertValidDescription(?string $description): void
    {
        if ($description === null) {
            return;
        }

        if ($description === '') {
            throw new InvalidArgumentException('Administration description cannot be empty; use null instead.');
        }

        if (preg_match('/\A\s|\s\z/u', $description) === 1) {
            throw new InvalidArgumentException('Administration description cannot contain leading or trailing whitespace.');
        }

        if (mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Administration description cannot exceed 1000 characters.');
        }
    }
}

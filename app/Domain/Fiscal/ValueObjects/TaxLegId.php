<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class TaxLegId
{
    public function __construct(private Uuid $uuid) {}

    public function uuid(): Uuid
    {
        return $this->uuid;
    }

    public function taxPostingId(): TaxPostingId
    {
        return new TaxPostingId($this->uuid);
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

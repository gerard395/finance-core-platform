<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class PurchaseCreditSourceLineClaimId
{
    public function __construct(private Uuid $uuid) {}

    public function toString(): string
    {
        return $this->uuid->toString();
    }
}

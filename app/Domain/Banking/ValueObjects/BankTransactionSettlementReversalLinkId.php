<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class BankTransactionSettlementReversalLinkId
{
    public function __construct(private Uuid $uuid) {}

    public function toString(): string
    {
        return $this->uuid->toString();
    }
}

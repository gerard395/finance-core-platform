<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class BankEntryReconciliationHistoryId
{
    public function __construct(private Uuid $value) {}

    public function toString(): string
    {
        return $this->value->toString();
    }
}

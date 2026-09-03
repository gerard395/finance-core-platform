<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class BankImportBatchId
{
    public function __construct(private Uuid $value) {}

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }
}

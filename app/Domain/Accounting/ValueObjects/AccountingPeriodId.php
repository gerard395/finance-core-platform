<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class AccountingPeriodId
{
    public function __construct(private Uuid $uuid) {}

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function equals(self $o): bool
    {
        return $this->uuid->equals($o->uuid);
    }
}

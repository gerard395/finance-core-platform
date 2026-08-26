<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use DateTimeImmutable;

final readonly class TransactionDate
{
    public function __construct(private DateTimeImmutable $value) {}

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }
}

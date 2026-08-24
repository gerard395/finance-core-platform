<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use DateTimeImmutable;

final readonly class SupplyDate
{
    private DateTimeImmutable $value;

    public function __construct(DateTimeImmutable $value)
    {
        $this->value = new DateTimeImmutable($value->format('Y-m-d'));
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }
}

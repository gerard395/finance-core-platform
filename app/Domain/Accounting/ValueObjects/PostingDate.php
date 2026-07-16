<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

use DateTimeImmutable;

final readonly class PostingDate
{
    public function __construct(private DateTimeImmutable $value) {}

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }
}

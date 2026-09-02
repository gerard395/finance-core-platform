<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use InvalidArgumentException;

final readonly class DeductibilityBasisPoints
{
    public function __construct(private int $value)
    {
        if ($value < 0 || $value > 10000) {
            throw new InvalidArgumentException('Deductibility basis points must be between 0 and 10000.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }
}

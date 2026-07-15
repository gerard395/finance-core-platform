<?php

declare(strict_types=1);

namespace App\Domain\Relations\ValueObjects;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/\A(?=.{3,32}\z)[+0-9][0-9 ()+.-]*[0-9]\z/', $value) !== 1) {
            throw new InvalidArgumentException('Phone number must contain 3 to 32 valid characters.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

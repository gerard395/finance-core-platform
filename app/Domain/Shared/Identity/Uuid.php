<?php

declare(strict_types=1);

namespace App\Domain\Shared\Identity;

use InvalidArgumentException;

final readonly class Uuid
{
    private const string NIL = '00000000-0000-0000-0000-000000000000';

    public string $value;

    public function __construct(string $value)
    {
        $normalizedValue = strtolower($value);

        if ($normalizedValue === self::NIL) {
            throw new InvalidArgumentException('Nil UUID is not allowed.');
        }

        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $normalizedValue) !== 1) {
            throw new InvalidArgumentException('Invalid UUID value.');
        }

        $this->value = $normalizedValue;
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

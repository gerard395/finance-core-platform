<?php

declare(strict_types=1);

namespace App\Domain\Shared\Finance;

use InvalidArgumentException;

final readonly class Currency
{
    private string $code;

    public function __construct(string $code)
    {
        if (preg_match('/\A[A-Za-z]{3}\z/', $code) !== 1) {
            throw new InvalidArgumentException('Currency code must contain exactly three ASCII letters.');
        }

        $this->code = strtoupper($code);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function toString(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}

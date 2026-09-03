<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use InvalidArgumentException;

final readonly class OriginalFileHash
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Original file hash must be lowercase SHA-256.');
        }
    }

    public static function fromBytes(string $bytes): self
    {
        return new self(hash('sha256', $bytes));
    }
}

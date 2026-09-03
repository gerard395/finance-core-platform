<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use InvalidArgumentException;

final readonly class ReconciliationReason
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 500) {
            throw new InvalidArgumentException('Reconciliation reason must contain 1 to 500 characters.');
        }
        $this->value = $value;
    }
}

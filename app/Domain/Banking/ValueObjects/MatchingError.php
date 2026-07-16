<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

final readonly class MatchingError
{
    public const string NO_PAYMENTS = 'NO_PAYMENTS';

    public const string AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';

    public const string INVALID_STATUS = 'INVALID_STATUS';

    public function __construct(
        private string $code,
        private string $message,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

final readonly class ValidationError
{
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

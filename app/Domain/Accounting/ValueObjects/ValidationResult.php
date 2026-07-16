<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

final readonly class ValidationResult
{
    /** @var list<ValidationError> */
    private array $errors;

    /** @param list<ValidationError> $errors */
    public function __construct(array $errors = [])
    {
        $this->errors = array_values($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<ValidationError> */
    public function errors(): array
    {
        return $this->errors;
    }
}

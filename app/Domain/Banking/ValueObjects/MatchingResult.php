<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Shared\Finance\Money;

final readonly class MatchingResult
{
    /** @var list<MatchingError> */
    private array $errors;

    /** @param list<MatchingError> $errors */
    public function __construct(
        private BankTransaction $transaction,
        private Money $matchedAmount,
        array $errors = [],
    ) {
        $this->errors = array_values($errors);
    }

    public function isSuccess(): bool
    {
        return $this->errors === [];
    }

    /** @return list<MatchingError> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function matchedAmount(): Money
    {
        return $this->matchedAmount;
    }

    public function transaction(): BankTransaction
    {
        return $this->transaction;
    }
}

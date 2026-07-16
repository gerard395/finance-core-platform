<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\Entities\JournalEntry;

final readonly class PostingResult
{
    /** @var list<ValidationError> */
    private array $validationErrors;

    /** @param list<ValidationError> $validationErrors */
    private function __construct(
        private ?JournalEntry $journalEntry,
        array $validationErrors,
    ) {
        $this->validationErrors = array_values($validationErrors);
    }

    public static function success(JournalEntry $journalEntry): self
    {
        return new self($journalEntry, []);
    }

    /** @param list<ValidationError> $validationErrors */
    public static function failure(array $validationErrors): self
    {
        return new self(null, $validationErrors);
    }

    public function isSuccess(): bool
    {
        return $this->journalEntry !== null;
    }

    public function journalEntry(): ?JournalEntry
    {
        return $this->journalEntry;
    }

    /** @return list<ValidationError> */
    public function validationErrors(): array
    {
        return $this->validationErrors;
    }
}

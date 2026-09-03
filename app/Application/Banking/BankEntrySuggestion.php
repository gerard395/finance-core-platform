<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Relations\ValueObjects\RelationId;

final readonly class BankEntrySuggestion
{
    /** @param list<string> $evidence @param list<PreparedPaymentAllocation> $allocations @param list<RelationId> $ambiguousRelations */
    public function __construct(
        public BankEntrySuggestionIntent $intent,
        public BankEntrySuggestionConfidence $confidence,
        public BankEntrySuggestionOutcome $outcome,
        public array $evidence,
        public ?RelationId $relationId = null,
        public array $allocations = [],
        public array $ambiguousRelations = [],
        public ?string $suggestedCategory = null,
    ) {}
}

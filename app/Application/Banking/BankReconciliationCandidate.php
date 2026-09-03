<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class BankReconciliationCandidate
{
    /** @param list<string> $relationIbans */
    public function __construct(
        public OpenItemId $openItemId,
        public RelationId $relationId,
        public string $relationName,
        public OpenItemType $type,
        public OpenItemSide $side,
        public LedgerAccountId $controlLedgerAccountId,
        public Money $openBalance,
        public DateTimeImmutable $openedOn,
        public ?DateTimeImmutable $dueDate,
        public string $documentReference,
        public array $relationIbans,
    ) {}
}

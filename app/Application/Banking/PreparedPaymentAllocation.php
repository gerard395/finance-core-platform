<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;

final readonly class PreparedPaymentAllocation
{
    /** @param list<string> $evidence */
    public function __construct(public OpenItemId $openItemId, public RelationId $relationId, public LedgerAccountId $historicalControlAccountId, public Money $amount, public Money $currentOpenBalance, public array $evidence) {}
}

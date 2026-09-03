<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Shared\Finance\Money;

final readonly class BankEntryFinancialAllocationSummary
{
    public function __construct(
        public PaymentAllocationId $allocationId,
        public OpenItemId $openItemId,
        public Money $amount,
        public OpenItemSettlementId $settlementId,
        public ?OpenItemSettlementId $reversalSettlementId,
    ) {}
}

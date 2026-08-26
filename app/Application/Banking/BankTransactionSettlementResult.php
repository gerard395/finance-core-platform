<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Shared\Finance\Money;

final readonly class BankTransactionSettlementResult
{
    public function __construct(
        public PaymentAllocationId $paymentAllocationId,
        public OpenItemId $openItemId,
        public Money $settlementAmount,
        public Money $remainingOpenAmount,
    ) {}
}

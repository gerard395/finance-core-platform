<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;

final readonly class BankTransactionReversalSettlementSource
{
    public function __construct(
        public PaymentAllocationId $paymentAllocationId,
        public OpenItemId $openItemId,
        public OpenItemSettlement $settlement,
        public bool $hasReversal,
    ) {}
}

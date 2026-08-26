<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankTransaction;

final readonly class BankTransactionPostingDetail
{
    /** @param list<BankTransactionSettlementResult> $settlements */
    public function __construct(
        public BankTransaction $bankTransaction,
        public BankTransactionPosting $posting,
        public array $settlements,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class ReconcileBankStatementEntryResult
{
    public function __construct(public ReconcileBankStatementEntryStatus $status, public ?BankEntryReconciliationId $reconciliationId = null, public ?BankTransactionId $bankTransactionId = null) {}
}

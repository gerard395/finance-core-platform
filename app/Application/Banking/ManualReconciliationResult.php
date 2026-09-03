<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;

final readonly class ManualReconciliationResult
{
    public function __construct(public ManualReconciliationStatus $status, public ?BankEntryReconciliationHistoryId $historyId = null) {}
}

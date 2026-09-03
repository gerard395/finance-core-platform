<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankImportBatchId;

final readonly class ConfirmBankImportResult
{
    public function __construct(public ConfirmBankImportStatus $status, public ?BankImportBatchId $batchId = null) {}
}

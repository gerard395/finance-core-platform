<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;

interface BankImportSourceIdentityGenerator
{
    public function batchId(): BankImportBatchId;

    public function statementId(): BankStatementId;

    public function entryId(): BankStatementEntryId;
}

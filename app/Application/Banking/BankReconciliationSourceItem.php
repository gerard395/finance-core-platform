<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementId;

final readonly class BankReconciliationSourceItem
{
    /** @param list<BankEntryReconciliationHistory> $manualHistory */
    public function __construct(public BankStatementEntry $entry, public AdministrationBankAccountId $bankAccountId, public BankStatementId $statementId, public BankImportBatchId $batchId, public ?string $statementExternalId, public BankEntryDerivedState $state, public array $manualHistory, public ?BankEntryFinancialSummary $financial = null) {}
}

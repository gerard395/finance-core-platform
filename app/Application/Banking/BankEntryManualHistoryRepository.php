<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;

interface BankEntryManualHistoryRepository
{
    /** Locks the immutable source row, the common coordination lock for BIR-003/004. */
    public function lockEntry(AdministrationId $administrationId, BankStatementEntryId $entryId): bool;

    public function latest(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliationHistory;

    /** @return list<BankEntryReconciliationHistory> */
    public function history(AdministrationId $administrationId, BankStatementEntryId $entryId): array;

    public function append(BankEntryReconciliationHistory $history): bool;

    /** BIR-004 implements the financial linkage behind this read without changing callers. */
    public function hasActiveReconciliation(AdministrationId $administrationId, BankStatementEntryId $entryId): bool;
}

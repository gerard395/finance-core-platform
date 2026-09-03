<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

interface BankEntryFinancialReconciliationStore
{
    public function lockSource(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryPromotionSource;

    public function active(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation;

    public function latest(AdministrationId $administrationId, BankStatementEntryId $entryId): ?BankEntryReconciliation;

    public function byTransaction(AdministrationId $administrationId, BankTransactionId $transactionId): ?BankEntryReconciliation;

    public function append(BankEntryReconciliation $reconciliation): bool;

    public function activate(BankEntryReconciliation $reconciliation): bool;

    public function deactivate(AdministrationId $administrationId, BankStatementEntryId $entryId, BankEntryReconciliationId $expected): bool;
}

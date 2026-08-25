<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;

final readonly class PurchasePostingConfigurationSettings
{
    /** @param list<Journal> $purchaseJournals @param list<LedgerAccount> $accountsPayableAccounts @param list<LedgerAccount> $inputVatAccounts */
    public function __construct(
        public PurchasePostingConfigurationReadResult $current,
        public ?Journal $currentPurchaseJournal,
        public ?LedgerAccount $currentAccountsPayableAccount,
        public ?LedgerAccount $currentInputVatAccount,
        public array $purchaseJournals,
        public array $accountsPayableAccounts,
        public array $inputVatAccounts,
    ) {}
}

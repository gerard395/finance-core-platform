<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;

final readonly class SalesPostingConfigurationSettings
{
    /**
     * @param  list<Journal>  $salesJournals
     * @param  list<LedgerAccount>  $accountsReceivableAccounts
     * @param  list<LedgerAccount>  $revenueAccounts
     * @param  list<LedgerAccount>  $outputVatAccounts
     */
    public function __construct(
        public SalesPostingConfigurationReadResult $current,
        public ?Journal $currentSalesJournal,
        public ?LedgerAccount $currentAccountsReceivableAccount,
        public ?LedgerAccount $currentRevenueAccount,
        public ?LedgerAccount $currentOutputVatAccount,
        public array $salesJournals,
        public array $accountsReceivableAccounts,
        public array $revenueAccounts,
        public array $outputVatAccounts,
    ) {}
}

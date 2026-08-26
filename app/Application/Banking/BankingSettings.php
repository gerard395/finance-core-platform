<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Banking\Entities\AdministrationBankAccount;

final readonly class BankingSettings
{
    /** @param list<AdministrationBankAccount> $bankAccounts @param array<string, BankingPostingConfigurationReadResult> $configurations @param list<Journal> $bankJournals @param list<LedgerAccount> $bankLedgerAccounts */
    public function __construct(public array $bankAccounts, public array $configurations, public array $bankJournals, public array $bankLedgerAccounts) {}
}

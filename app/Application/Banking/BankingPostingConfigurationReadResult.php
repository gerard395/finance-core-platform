<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Banking\Entities\AdministrationBankAccount;

final readonly class BankingPostingConfigurationReadResult
{
    /** @param list<BankingPostingConfigurationInvalidReference> $invalidReferences */
    private function __construct(public BankingPostingConfigurationReadStatus $status, public ?BankingPostingConfiguration $configuration, public ?AdministrationBankAccount $bankAccount, public ?Journal $bankJournal, public ?LedgerAccount $bankLedgerAccount, public array $invalidReferences) {}

    public static function missing(): self
    {
        return new self(BankingPostingConfigurationReadStatus::Missing, null, null, null, null, []);
    }

    public static function success(BankingPostingConfiguration $configuration, AdministrationBankAccount $account, Journal $journal, LedgerAccount $ledger): self
    {
        return new self(BankingPostingConfigurationReadStatus::Success, $configuration, $account, $journal, $ledger, []);
    }

    /** @param non-empty-list<BankingPostingConfigurationInvalidReference> $invalid */
    public static function invalid(BankingPostingConfiguration $configuration, ?AdministrationBankAccount $account, ?Journal $journal, ?LedgerAccount $ledger, array $invalid): self
    {
        return new self(BankingPostingConfigurationReadStatus::InvalidReference, $configuration, $account, $journal, $ledger, $invalid);
    }
}

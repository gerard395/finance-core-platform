<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetBankingSettings
{
    public function __construct(private AdministrationBankAccountRepository $bankAccounts, private BankingPostingConfigurationReader $configurations, private JournalReadRepository $journals, private LedgerAccountReadRepository $accounts) {}

    public function execute(AdministrationId $administrationId): BankingSettings
    {
        $bankAccounts = $this->bankAccounts->findForAdministration($administrationId);
        $configurations = [];
        foreach ($bankAccounts as $account) {
            $configurations[$account->id()->toString()] = $this->configurations->read($administrationId, $account->id());
        }

        return new BankingSettings(
            $bankAccounts,
            $configurations,
            array_values(array_filter($this->journals->findForAdministration($administrationId), static fn ($journal) => $journal->isActive() && $journal->type() === JournalType::Bank)),
            array_values(array_filter($this->accounts->findForAdministration($administrationId), static fn ($account) => $account->isActive() && $account->type() === LedgerAccountType::Asset)),
        );
    }
}

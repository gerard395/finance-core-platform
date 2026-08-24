<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetSalesPostingConfigurationSettings
{
    public function __construct(
        private SalesPostingConfigurationReader $configurations,
        private JournalReadRepository $journals,
        private LedgerAccountReadRepository $accounts,
    ) {}

    public function execute(AdministrationId $administrationId): SalesPostingConfigurationSettings
    {
        $allJournals = $this->journals->findForAdministration($administrationId);
        $journals = array_values(array_filter(
            $allJournals,
            static fn ($journal): bool => $journal->isActive() && $journal->type() === JournalType::Sales,
        ));
        $accounts = $this->accounts->findForAdministration($administrationId);
        $current = $this->configurations->read($administrationId);
        $configuration = $current->configuration();

        return new SalesPostingConfigurationSettings(
            $current,
            $configuration === null ? null : $this->journal($allJournals, $configuration->salesJournalId()),
            $configuration === null ? null : $this->account($accounts, $configuration->accountsReceivableLedgerAccountId()),
            $configuration === null ? null : $this->account($accounts, $configuration->revenueLedgerAccountId()),
            $configuration === null ? null : $this->account($accounts, $configuration->outputVatLedgerAccountId()),
            $journals,
            $this->eligibleAccounts($accounts, LedgerAccountType::Asset),
            $this->eligibleAccounts($accounts, LedgerAccountType::Revenue),
            $this->eligibleAccounts($accounts, LedgerAccountType::Liability),
        );
    }

    /** @param list<Journal> $journals */
    private function journal(array $journals, JournalId $id): ?Journal
    {
        foreach ($journals as $journal) {
            if ($journal->id()->equals($id)) {
                return $journal;
            }
        }

        return null;
    }

    /** @param list<LedgerAccount> $accounts */
    private function account(array $accounts, LedgerAccountId $id): ?LedgerAccount
    {
        foreach ($accounts as $account) {
            if ($account->id()->equals($id)) {
                return $account;
            }
        }

        return null;
    }

    private function eligibleAccounts(array $accounts, LedgerAccountType $type): array
    {
        return array_values(array_filter(
            $accounts,
            static fn ($account): bool => $account->isActive() && $account->type() === $type,
        ));
    }
}

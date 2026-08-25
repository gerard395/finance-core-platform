<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetPurchasePostingConfigurationSettings
{
    public function __construct(private PurchasePostingConfigurationReader $configurations, private JournalReadRepository $journals, private LedgerAccountReadRepository $accounts) {}

    public function execute(AdministrationId $administrationId): PurchasePostingConfigurationSettings
    {
        $allJournals = $this->journals->findForAdministration($administrationId);
        $allAccounts = $this->accounts->findForAdministration($administrationId);
        $current = $this->configurations->read($administrationId);
        $configuration = $current->configuration;

        return new PurchasePostingConfigurationSettings(
            $current,
            $configuration === null ? null : $this->journal($allJournals, $configuration->purchaseJournalId),
            $configuration === null ? null : $this->account($allAccounts, $configuration->accountsPayableLedgerAccountId),
            $configuration === null ? null : $this->account($allAccounts, $configuration->inputVatLedgerAccountId),
            array_values(array_filter($allJournals, static fn ($journal): bool => $journal->isActive() && $journal->type() === JournalType::Purchase)),
            $this->eligibleAccounts($allAccounts, LedgerAccountType::Liability),
            $this->eligibleAccounts($allAccounts, LedgerAccountType::Asset),
        );
    }

    private function journal(array $journals, JournalId $id): mixed
    {
        foreach ($journals as $journal) {
            if ($journal->id()->equals($id)) {
                return $journal;
            }
        }

        return null;
    }

    private function account(array $accounts, LedgerAccountId $id): mixed
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
        return array_values(array_filter($accounts, static fn ($account): bool => $account->isActive() && $account->type() === $type));
    }
}

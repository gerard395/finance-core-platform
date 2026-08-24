<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class UpdateSalesPostingConfiguration
{
    public function __construct(
        private TransactionManager $transactions,
        private JournalReadRepository $journals,
        private LedgerAccountReadRepository $accounts,
        private SalesPostingConfigurationStore $configurations,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        JournalId $salesJournalId,
        LedgerAccountId $accountsReceivableLedgerAccountId,
        LedgerAccountId $revenueLedgerAccountId,
        LedgerAccountId $outputVatLedgerAccountId,
    ): UpdateSalesPostingConfigurationResult {
        return $this->transactions->run(function () use ($administrationId, $salesJournalId, $accountsReceivableLedgerAccountId, $revenueLedgerAccountId, $outputVatLedgerAccountId): UpdateSalesPostingConfigurationResult {
            $journal = $this->journals->findByIdForAdministration($administrationId, $salesJournalId);
            $accounts = $this->accounts->findForAdministration($administrationId);

            if ($journal === null || ! $journal->isActive() || $journal->type() !== JournalType::Sales
                || ! $this->eligible($accounts, $accountsReceivableLedgerAccountId, LedgerAccountType::Asset)
                || ! $this->eligible($accounts, $revenueLedgerAccountId, LedgerAccountType::Revenue)
                || ! $this->eligible($accounts, $outputVatLedgerAccountId, LedgerAccountType::Liability)) {
                return UpdateSalesPostingConfigurationResult::InvalidReference;
            }

            $result = $this->configurations->save(new SalesPostingConfiguration(
                $administrationId,
                $salesJournalId,
                $accountsReceivableLedgerAccountId,
                $revenueLedgerAccountId,
                $outputVatLedgerAccountId,
            ));

            return $result === SalesPostingConfigurationWriteResult::Saved
                ? UpdateSalesPostingConfigurationResult::Saved
                : UpdateSalesPostingConfigurationResult::InvalidReference;
        });
    }

    /** @param list<LedgerAccount> $accounts */
    private function eligible(array $accounts, LedgerAccountId $id, LedgerAccountType $type): bool
    {
        foreach ($accounts as $account) {
            if ($account->id()->equals($id)) {
                return $account->isActive() && $account->type() === $type;
            }
        }

        return false;
    }
}

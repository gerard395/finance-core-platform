<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class UpdatePurchasePostingConfiguration
{
    public function __construct(
        private TransactionManager $transactions,
        private JournalReadRepository $journals,
        private LedgerAccountReadRepository $accounts,
        private PurchasePostingConfigurationStore $configurations,
    ) {}

    public function execute(AdministrationId $administrationId, JournalId $journalId, LedgerAccountId $payableId, LedgerAccountId $inputVatId): UpdatePurchasePostingConfigurationResult
    {
        return $this->transactions->run(function () use ($administrationId, $journalId, $payableId, $inputVatId): UpdatePurchasePostingConfigurationResult {
            $journal = $this->journals->findByIdForAdministration($administrationId, $journalId);
            $accounts = $this->accounts->findForAdministration($administrationId);
            if ($journal === null || ! $journal->isActive() || $journal->type() !== JournalType::Purchase
                || ! $this->eligible($accounts, $payableId, LedgerAccountType::Liability)
                || ! $this->eligible($accounts, $inputVatId, LedgerAccountType::Asset)) {
                return UpdatePurchasePostingConfigurationResult::InvalidReference;
            }

            return $this->configurations->save(new PurchasePostingConfiguration($administrationId, $journalId, $payableId, $inputVatId))
                ? UpdatePurchasePostingConfigurationResult::Saved
                : UpdatePurchasePostingConfigurationResult::InvalidReference;
        });
    }

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

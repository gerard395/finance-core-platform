<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

final readonly class UpdateBankingPostingConfiguration
{
    public function __construct(private TransactionManager $transactions, private AdministrationBankAccountRepository $bankAccounts, private JournalReadRepository $journals, private LedgerAccountReadRepository $accounts, private BankingPostingConfigurationStore $configurations) {}

    public function execute(AdministrationId $administrationId, AdministrationBankAccountId $bankAccountId, JournalId $journalId, LedgerAccountId $ledgerId): UpdateBankingPostingConfigurationResult
    {
        return $this->transactions->run(function () use ($administrationId, $bankAccountId, $journalId, $ledgerId): UpdateBankingPostingConfigurationResult {
            $bank = $this->bankAccounts->find($administrationId, $bankAccountId);
            $journal = $this->journals->findByIdForAdministration($administrationId, $journalId);
            $ledger = null;
            foreach ($this->accounts->findForAdministration($administrationId) as $candidate) {
                if ($candidate->id()->equals($ledgerId)) {
                    $ledger = $candidate;
                    break;
                }
            }
            if ($bank === null || ! $bank->isActive() || $journal === null || ! $journal->isActive() || $journal->type() !== JournalType::Bank || $ledger === null || ! $ledger->isActive() || $ledger->type() !== LedgerAccountType::Asset) {
                return UpdateBankingPostingConfigurationResult::InvalidReference;
            }

            return $this->configurations->save(new BankingPostingConfiguration($administrationId, $bankAccountId, $journalId, $ledgerId)) ? UpdateBankingPostingConfigurationResult::Saved : UpdateBankingPostingConfigurationResult::InvalidReference;
        });
    }
}

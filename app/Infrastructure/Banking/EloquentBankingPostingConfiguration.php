<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\BankingPostingConfiguration;
use App\Application\Banking\BankingPostingConfigurationInvalidReference;
use App\Application\Banking\BankingPostingConfigurationReader;
use App\Application\Banking\BankingPostingConfigurationReadResult;
use App\Application\Banking\BankingPostingConfigurationStore;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\BankingPostingConfigurationRecord;

final readonly class EloquentBankingPostingConfiguration implements BankingPostingConfigurationReader, BankingPostingConfigurationStore
{
    public function __construct(private AdministrationBankAccountRepository $bankAccounts, private JournalReadRepository $journals, private LedgerAccountReadRepository $accounts) {}

    public function read(AdministrationId $administrationId, AdministrationBankAccountId $bankAccountId): BankingPostingConfigurationReadResult
    {
        $record = BankingPostingConfigurationRecord::query()->where('administration_id', $administrationId->toString())->where('administration_bank_account_id', $bankAccountId->toString())->first();
        if ($record === null) {
            return BankingPostingConfigurationReadResult::missing();
        }
        $configuration = new BankingPostingConfiguration($administrationId, $bankAccountId, new JournalId(new Uuid($record->getAttribute('bank_journal_id'))), new LedgerAccountId(new Uuid($record->getAttribute('bank_ledger_account_id'))));
        $bank = $this->bankAccounts->find($administrationId, $bankAccountId);
        $journal = $this->journals->findByIdForAdministration($administrationId, $configuration->bankJournalId);
        $ledger = null;
        foreach ($this->accounts->findForAdministration($administrationId) as $candidate) {
            if ($candidate->id()->equals($configuration->bankLedgerAccountId)) {
                $ledger = $candidate;
                break;
            }
        }
        $invalid = [];
        if ($bank === null || ! $bank->isActive()) {
            $invalid[] = BankingPostingConfigurationInvalidReference::BankAccount;
        }
        if ($journal === null || ! $journal->isActive() || $journal->type() !== JournalType::Bank) {
            $invalid[] = BankingPostingConfigurationInvalidReference::BankJournal;
        }
        if ($ledger === null || ! $ledger->isActive() || $ledger->type() !== LedgerAccountType::Asset) {
            $invalid[] = BankingPostingConfigurationInvalidReference::BankLedgerAccount;
        }

        return $invalid === [] ? BankingPostingConfigurationReadResult::success($configuration, $bank, $journal, $ledger) : BankingPostingConfigurationReadResult::invalid($configuration, $bank, $journal, $ledger, $invalid);
    }

    public function save(BankingPostingConfiguration $configuration): bool
    {
        BankingPostingConfigurationRecord::query()->updateOrCreate(['administration_id' => $configuration->administrationId->toString(), 'administration_bank_account_id' => $configuration->bankAccountId->toString()], ['bank_journal_id' => $configuration->bankJournalId->toString(), 'bank_ledger_account_id' => $configuration->bankLedgerAccountId->toString()]);

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

final readonly class AssessBankImportPreview
{
    public function __construct(private AdministrationBankAccountRepository $accounts, private BankImportSourceRepository $sources) {}

    public function execute(AdministrationId $administrationId, AdministrationBankAccountId $bankAccountId, BankStatementParseResult $parsed): ConfirmBankImportStatus
    {
        if ($parsed->status !== BankStatementParseStatus::Success || $parsed->namespace === null || $parsed->originalFileHash === null) {
            return match ($parsed->status) {
                BankStatementParseStatus::UnsupportedCurrency => ConfirmBankImportStatus::UnsupportedCurrency,
                BankStatementParseStatus::BankAccountMismatch => ConfirmBankImportStatus::BankAccountMismatch,
                default => ConfirmBankImportStatus::IntegrityFailure,
            };
        }
        $account = $this->accounts->find($administrationId, $bankAccountId);
        if ($account === null) {
            return ConfirmBankImportStatus::NotFound;
        }
        if (! $account->isActive() || $account->currency()->code() !== 'EUR') {
            return ConfirmBankImportStatus::UnsupportedCurrency;
        }
        foreach ($this->sources->list($administrationId) as $existing) {
            if ($existing->bankAccountId->equals($bankAccountId) && hash_equals($existing->originalFileHash->value, $parsed->originalFileHash->value)) {
                return ConfirmBankImportStatus::DuplicateBatch;
            }
        }
        foreach ($parsed->statements as $statement) {
            if ($statement->currency !== 'EUR') {
                return ConfirmBankImportStatus::UnsupportedCurrency;
            }
            if ($statement->accountIdentity !== $account->iban()->value()) {
                return ConfirmBankImportStatus::BankAccountMismatch;
            }
            if ($statement->openingBalance === null || $statement->closingBalance === null) {
                return ConfirmBankImportStatus::MissingStatementBalance;
            }
            $closing = $statement->openingBalance;
            foreach ($statement->entries as $entry) {
                $closing = $closing->add($entry->amount);
            }
            if (! $closing->equals($statement->closingBalance)) {
                return ConfirmBankImportStatus::StatementBalanceMismatch;
            }
        }

        return ConfirmBankImportStatus::Success;
    }
}

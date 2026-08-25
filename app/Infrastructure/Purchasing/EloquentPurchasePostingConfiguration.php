<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchasePostingConfiguration;
use App\Application\Purchasing\PurchasePostingConfigurationInvalidReference;
use App\Application\Purchasing\PurchasePostingConfigurationReader;
use App\Application\Purchasing\PurchasePostingConfigurationReadResult;
use App\Application\Purchasing\PurchasePostingConfigurationStore;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PurchasePostingConfigurationRecord;

final class EloquentPurchasePostingConfiguration implements PurchasePostingConfigurationReader, PurchasePostingConfigurationStore
{
    public function read(AdministrationId $administrationId): PurchasePostingConfigurationReadResult
    {
        $record = PurchasePostingConfigurationRecord::query()->find($administrationId->toString());
        if ($record === null) {
            return PurchasePostingConfigurationReadResult::missing();
        }
        $configuration = $this->hydrate($record);
        $invalid = $this->invalidReferences($configuration);

        return $invalid === [] ? PurchasePostingConfigurationReadResult::success($configuration) : PurchasePostingConfigurationReadResult::invalidReference($configuration, $invalid);
    }

    public function save(PurchasePostingConfiguration $configuration): bool
    {
        if ($this->invalidReferences($configuration) !== []) {
            return false;
        }
        PurchasePostingConfigurationRecord::query()->updateOrCreate(
            ['administration_id' => $configuration->administrationId->toString()],
            ['purchase_journal_id' => $configuration->purchaseJournalId->toString(), 'accounts_payable_ledger_account_id' => $configuration->accountsPayableLedgerAccountId->toString(), 'input_vat_ledger_account_id' => $configuration->inputVatLedgerAccountId->toString()],
        );

        return true;
    }

    /** @return list<PurchasePostingConfigurationInvalidReference> */
    private function invalidReferences(PurchasePostingConfiguration $configuration): array
    {
        $admin = $configuration->administrationId->toString();
        $invalid = [];
        if (! JournalRecord::query()->where('administration_id', $admin)->whereKey($configuration->purchaseJournalId->toString())->where('status', JournalStatus::Active->value)->where('type', JournalType::Purchase->value)->exists()) {
            $invalid[] = PurchasePostingConfigurationInvalidReference::PurchaseJournal;
        }
        if (! LedgerAccountRecord::query()->where('administration_id', $admin)->whereKey($configuration->accountsPayableLedgerAccountId->toString())->where('status', LedgerAccountStatus::Active->value)->where('type', LedgerAccountType::Liability->value)->exists()) {
            $invalid[] = PurchasePostingConfigurationInvalidReference::AccountsPayable;
        }
        if (! LedgerAccountRecord::query()->where('administration_id', $admin)->whereKey($configuration->inputVatLedgerAccountId->toString())->where('status', LedgerAccountStatus::Active->value)->where('type', LedgerAccountType::Asset->value)->exists()) {
            $invalid[] = PurchasePostingConfigurationInvalidReference::InputVat;
        }

        return $invalid;
    }

    private function hydrate(PurchasePostingConfigurationRecord $record): PurchasePostingConfiguration
    {
        return new PurchasePostingConfiguration(new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new JournalId(new Uuid($record->getAttribute('purchase_journal_id'))), new LedgerAccountId(new Uuid($record->getAttribute('accounts_payable_ledger_account_id'))), new LedgerAccountId(new Uuid($record->getAttribute('input_vat_ledger_account_id'))));
    }
}

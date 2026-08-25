<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesPostingConfiguration;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationReadResult;
use App\Application\Sales\SalesPostingConfigurationStore;
use App\Application\Sales\SalesPostingConfigurationWriteResult;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesPostingConfigurationRecord;

final class EloquentSalesPostingConfiguration implements SalesPostingConfigurationReader, SalesPostingConfigurationStore
{
    public function read(AdministrationId $administrationId): SalesPostingConfigurationReadResult
    {
        $record = SalesPostingConfigurationRecord::query()->find($administrationId->toString());

        if ($record === null) {
            return SalesPostingConfigurationReadResult::missing();
        }

        $configuration = $this->hydrate($record);

        return $this->referencesAreValid($configuration)
            ? SalesPostingConfigurationReadResult::success($configuration)
            : SalesPostingConfigurationReadResult::invalidReference($configuration);
    }

    public function save(SalesPostingConfiguration $configuration): SalesPostingConfigurationWriteResult
    {
        if (! $this->referencesAreValid($configuration)) {
            return SalesPostingConfigurationWriteResult::InvalidReference;
        }

        SalesPostingConfigurationRecord::query()->updateOrCreate(
            ['administration_id' => $configuration->administrationId()->toString()],
            [
                'sales_journal_id' => $configuration->salesJournalId()->toString(),
                'accounts_receivable_ledger_account_id' => $configuration->accountsReceivableLedgerAccountId()->toString(),
                'revenue_ledger_account_id' => $configuration->revenueLedgerAccountId()->toString(),
                'output_vat_ledger_account_id' => $configuration->outputVatLedgerAccountId()->toString(),
            ],
        );

        return SalesPostingConfigurationWriteResult::Saved;
    }

    private function referencesAreValid(SalesPostingConfiguration $configuration): bool
    {
        $administrationId = $configuration->administrationId()->toString();
        $journal = JournalRecord::query()->where('administration_id', $administrationId)->whereKey($configuration->salesJournalId()->toString())->first();

        if ($journal === null
            || $journal->getAttribute('status') !== JournalStatus::Active->value
            || $journal->getAttribute('type') !== JournalType::Sales->value) {
            return false;
        }

        foreach ([
            $configuration->accountsReceivableLedgerAccountId(),
            $configuration->revenueLedgerAccountId(),
            $configuration->outputVatLedgerAccountId(),
        ] as $ledgerAccountId) {
            if (! LedgerAccountRecord::query()
                ->where('administration_id', $administrationId)
                ->whereKey($ledgerAccountId->toString())
                ->where('status', LedgerAccountStatus::Active->value)
                ->exists()) {
                return false;
            }
        }

        return true;
    }

    private function hydrate(SalesPostingConfigurationRecord $record): SalesPostingConfiguration
    {
        return new SalesPostingConfiguration(
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new JournalId(new Uuid($record->getAttribute('sales_journal_id'))),
            new LedgerAccountId(new Uuid($record->getAttribute('accounts_receivable_ledger_account_id'))),
            new LedgerAccountId(new Uuid($record->getAttribute('revenue_ledger_account_id'))),
            new LedgerAccountId(new Uuid($record->getAttribute('output_vat_ledger_account_id'))),
        );
    }
}

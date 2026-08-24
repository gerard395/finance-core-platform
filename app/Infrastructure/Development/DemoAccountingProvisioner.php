<?php

declare(strict_types=1);

namespace App\Infrastructure\Development;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioningConflict;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioningResult;
use App\Application\Sales\SalesPostingConfiguration;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationReadStatus;
use App\Application\Sales\SalesPostingConfigurationStore;
use App\Application\Sales\SalesPostingConfigurationWriteResult;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid as DomainUuid;
use DomainException;
use LogicException;
use Ramsey\Uuid\Uuid;

final readonly class DemoAccountingProvisioner implements DevelopmentAccountingMasterDataProvisioner
{
    private const string ID_NAMESPACE = '712ac50d-f78b-4b60-aef4-7375ca96e509';

    public function __construct(
        private TransactionManager $transactions,
        private JournalReadRepository $journalReader,
        private JournalStore $journalStore,
        private LedgerAccountReadRepository $ledgerAccountReader,
        private LedgerAccountStore $ledgerAccountStore,
        private SalesPostingConfigurationReader $configurationReader,
        private SalesPostingConfigurationStore $configurationStore,
    ) {}

    public function provision(AdministrationId $administrationId): DevelopmentAccountingMasterDataProvisioningResult
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Demo accounting provisioning is only available in development environments.');
        }

        return $this->transactions->run(function () use ($administrationId): DevelopmentAccountingMasterDataProvisioningResult {
            $journal = $this->journal(
                $administrationId,
                new Journal(
                    $this->journalId($administrationId, 'VERK'),
                    new JournalCode('VERK'),
                    new JournalName('Verkoop'),
                    JournalType::Sales,
                    JournalStatus::Active,
                ),
            );
            $accountsReceivable = $this->ledgerAccount(
                $administrationId,
                new LedgerAccount(
                    $this->ledgerAccountId($administrationId, '1300'),
                    new LedgerAccountCode('1300'),
                    new LedgerAccountName('Debiteuren'),
                    LedgerAccountType::Asset,
                    LedgerAccountStatus::Active,
                ),
            );
            $revenue = $this->ledgerAccount(
                $administrationId,
                new LedgerAccount(
                    $this->ledgerAccountId($administrationId, '8000'),
                    new LedgerAccountCode('8000'),
                    new LedgerAccountName('Omzet'),
                    LedgerAccountType::Revenue,
                    LedgerAccountStatus::Active,
                ),
            );
            $outputVat = $this->ledgerAccount(
                $administrationId,
                new LedgerAccount(
                    $this->ledgerAccountId($administrationId, '1600'),
                    new LedgerAccountCode('1600'),
                    new LedgerAccountName('Af te dragen btw'),
                    LedgerAccountType::Liability,
                    LedgerAccountStatus::Active,
                ),
            );
            $configuration = new SalesPostingConfiguration(
                $administrationId,
                $journal->id(),
                $accountsReceivable->id(),
                $revenue->id(),
                $outputVat->id(),
            );
            $this->configuration($configuration);

            return new DevelopmentAccountingMasterDataProvisioningResult(
                $journal,
                $accountsReceivable,
                $revenue,
                $outputVat,
                $configuration,
            );
        });
    }

    private function journal(AdministrationId $administrationId, Journal $definition): Journal
    {
        foreach ($this->journalReader->findForAdministration($administrationId) as $existing) {
            if ($existing->id()->equals($definition->id()) || $existing->code()->equals($definition->code())) {
                if (! $existing->id()->equals($definition->id())
                    || ! $existing->code()->equals($definition->code())
                    || ! $existing->name()->equals($definition->name())
                    || $existing->type() !== $definition->type()
                    || $existing->status() !== $definition->status()) {
                    throw new DevelopmentAccountingMasterDataProvisioningConflict('Existing development Sales Journal conflicts with VERK.');
                }

                return $existing;
            }
        }

        try {
            $this->journalStore->save($administrationId, $definition);
        } catch (DomainException $exception) {
            throw new DevelopmentAccountingMasterDataProvisioningConflict('Stable development Sales Journal identity is already in use.', previous: $exception);
        }

        return $definition;
    }

    private function ledgerAccount(AdministrationId $administrationId, LedgerAccount $definition): LedgerAccount
    {
        foreach ($this->ledgerAccountReader->findForAdministration($administrationId) as $existing) {
            if ($existing->id()->equals($definition->id()) || $existing->code()->equals($definition->code())) {
                if (! $existing->id()->equals($definition->id())
                    || ! $existing->code()->equals($definition->code())
                    || ! $existing->name()->equals($definition->name())
                    || $existing->type() !== $definition->type()
                    || $existing->status() !== $definition->status()) {
                    throw new DevelopmentAccountingMasterDataProvisioningConflict(
                        "Existing development ledger account conflicts with {$definition->code()->value()}.",
                    );
                }

                return $existing;
            }
        }

        try {
            $this->ledgerAccountStore->save($administrationId, $definition);
        } catch (DomainException $exception) {
            throw new DevelopmentAccountingMasterDataProvisioningConflict(
                "Stable development ledger account identity for {$definition->code()->value()} is already in use.",
                previous: $exception,
            );
        }

        return $definition;
    }

    private function configuration(SalesPostingConfiguration $definition): void
    {
        $existing = $this->configurationReader->read($definition->administrationId());
        if ($existing->status() === SalesPostingConfigurationReadStatus::Success) {
            $configuration = $existing->configuration();
            if ($configuration === null
                || ! $configuration->salesJournalId()->equals($definition->salesJournalId())
                || ! $configuration->accountsReceivableLedgerAccountId()->equals($definition->accountsReceivableLedgerAccountId())
                || ! $configuration->revenueLedgerAccountId()->equals($definition->revenueLedgerAccountId())
                || ! $configuration->outputVatLedgerAccountId()->equals($definition->outputVatLedgerAccountId())) {
                throw new DevelopmentAccountingMasterDataProvisioningConflict('Existing Sales posting configuration conflicts with Demo accounting master data.');
            }

            return;
        }
        if ($existing->status() === SalesPostingConfigurationReadStatus::InvalidReference) {
            throw new DevelopmentAccountingMasterDataProvisioningConflict('Existing Sales posting configuration contains invalid references.');
        }
        if ($this->configurationStore->save($definition) !== SalesPostingConfigurationWriteResult::Saved) {
            throw new DevelopmentAccountingMasterDataProvisioningConflict('Sales posting configuration rejected the provisioned development master data.');
        }
    }

    private function journalId(AdministrationId $administrationId, string $code): JournalId
    {
        return new JournalId(new DomainUuid(Uuid::uuid5(self::ID_NAMESPACE, $administrationId->toString().':journal:'.$code)->toString()));
    }

    private function ledgerAccountId(AdministrationId $administrationId, string $code): LedgerAccountId
    {
        return new LedgerAccountId(new DomainUuid(Uuid::uuid5(self::ID_NAMESPACE, $administrationId->toString().':ledger-account:'.$code)->toString()));
    }
}

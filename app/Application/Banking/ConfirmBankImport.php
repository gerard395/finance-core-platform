<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankImportBatch;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Banking\ValueObjects\SourceFormat;
use App\Domain\Identity\ValueObjects\UserId;
use RuntimeException;
use Throwable;

final readonly class ConfirmBankImport
{
    public function __construct(private TransactionManager $transactions, private AdministrationBankAccountRepository $accounts, private BankImportSourceRepository $sources, private BankImportSourceIdentityGenerator $identities, private BankImportArtifactStorage $storage, private BankImportArtifactKeyGenerator $keys, private BankImportClock $clock) {}

    public function execute(AdministrationId $administrationId, AdministrationBankAccountId $bankAccountId, BankStatementParseResult $parsed, StoredBankImportArtifact $artifact, UserId $actorId): ConfirmBankImportResult
    {
        if ($parsed->status !== BankStatementParseStatus::Success) {
            return new ConfirmBankImportResult(match ($parsed->status) {
                BankStatementParseStatus::UnsupportedCurrency => ConfirmBankImportStatus::UnsupportedCurrency,
                BankStatementParseStatus::BankAccountMismatch => ConfirmBankImportStatus::BankAccountMismatch,
                default => ConfirmBankImportStatus::IntegrityFailure,
            });
        }
        if ($parsed->namespace === null || $parsed->originalFileHash === null || ! hash_equals($parsed->originalFileHash->value, $artifact->hash->value)) {
            return new ConfirmBankImportResult(ConfirmBankImportStatus::IntegrityFailure);
        }
        $bytes = $this->storage->read($artifact->storageKey);
        if ($bytes === null || ! hash_equals($artifact->hash->value, hash('sha256', $bytes))) {
            return new ConfirmBankImportResult(ConfirmBankImportStatus::StorageFailure);
        }
        $retainedKey = $this->keys->retainedKey($artifact->hash->value);
        $promoted = false;
        try {
            return $this->transactions->run(function () use ($administrationId, $bankAccountId, $parsed, $artifact, $actorId, $retainedKey, &$promoted): ConfirmBankImportResult {
                $account = $this->accounts->lock($administrationId, $bankAccountId);
                if ($account === null) {
                    throw new ConfirmBankImportFailure(ConfirmBankImportStatus::NotFound);
                }
                if (! $account->isActive() || $account->currency()->code() !== 'EUR') {
                    throw new ConfirmBankImportFailure(ConfirmBankImportStatus::UnsupportedCurrency);
                }
                foreach ($parsed->statements as $statement) {
                    if ($statement->currency !== 'EUR') {
                        throw new ConfirmBankImportFailure(ConfirmBankImportStatus::UnsupportedCurrency);
                    }
                    if ($statement->accountIdentity !== $account->iban()->value()) {
                        throw new ConfirmBankImportFailure(ConfirmBankImportStatus::BankAccountMismatch);
                    }
                    if ($statement->openingBalance === null || $statement->closingBalance === null) {
                        throw new ConfirmBankImportFailure(ConfirmBankImportStatus::MissingStatementBalance);
                    }
                    $balance = $statement->openingBalance;
                    foreach ($statement->entries as $entry) {
                        $balance = $balance->add($entry->amount);
                    }
                    if (! $balance->equals($statement->closingBalance)) {
                        throw new ConfirmBankImportFailure(ConfirmBankImportStatus::StatementBalanceMismatch);
                    }
                }
                $batch = new BankImportBatch($this->identities->batchId(), $administrationId, $bankAccountId, SourceFormat::Camt053, $parsed->namespace, $artifact->hash, $parsed->namespace->parserVersion(), new CanonicalizationVersion, $actorId, $this->clock->now(), $retainedKey, $parsed->statements);
                if (($conflict = $this->sources->conflict($batch)) !== null) {
                    throw new ConfirmBankImportFailure($conflict);
                }
                if (! $this->storage->promoteToRetained($artifact->storageKey, $retainedKey, $artifact->hash->value)) {
                    throw new ConfirmBankImportFailure(ConfirmBankImportStatus::StorageFailure);
                }
                $promoted = true;
                if (! $this->sources->insert($batch)) {
                    throw new ConfirmBankImportFailure(ConfirmBankImportStatus::IntegrityFailure);
                }

                return new ConfirmBankImportResult(ConfirmBankImportStatus::Success, $batch->id);
            });
        } catch (ConfirmBankImportFailure $failure) {
            if ($promoted) {
                $this->storage->restoreToQuarantine($retainedKey, $artifact->storageKey, $artifact->hash->value);
            }

            return new ConfirmBankImportResult($failure->status);
        } catch (Throwable) {
            if ($promoted) {
                $this->storage->restoreToQuarantine($retainedKey, $artifact->storageKey, $artifact->hash->value);
            }

            return new ConfirmBankImportResult(ConfirmBankImportStatus::ConcurrencyConflict);
        }
    }
}

final class ConfirmBankImportFailure extends RuntimeException
{
    public function __construct(public readonly ConfirmBankImportStatus $status)
    {
        parent::__construct($status->value);
    }
}

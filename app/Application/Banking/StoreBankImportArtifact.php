<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\OriginalFileHash;
use Throwable;

final readonly class StoreBankImportArtifact
{
    public function __construct(private BankImportArtifactStorage $storage, private BankImportArtifactKeyGenerator $keys) {}

    public function execute(string $bytes): StoreBankImportArtifactResult
    {
        $hash = OriginalFileHash::fromBytes($bytes);
        $temporary = $this->keys->temporaryKey();
        $success = false;
        try {
            if (! $this->storage->storeImmutable($temporary, $bytes)) {
                return new StoreBankImportArtifactResult(StoreBankImportArtifactStatus::StorageFailure);
            }
            $stored = $this->storage->read($temporary);
            if ($stored === null || ! hash_equals($hash->value, hash('sha256', $stored))) {
                return new StoreBankImportArtifactResult(StoreBankImportArtifactStatus::IntegrityFailure);
            }

            $success = true;

            return new StoreBankImportArtifactResult(StoreBankImportArtifactStatus::Success, new StoredBankImportArtifact($temporary, $hash, strlen($bytes)));
        } catch (Throwable) {
            return new StoreBankImportArtifactResult(StoreBankImportArtifactStatus::StorageFailure);
        } finally {
            if (! $success) {
                try {
                    $this->storage->deleteTemporary($temporary);
                } catch (Throwable) {
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportArtifactStorage;
use Illuminate\Support\Facades\Storage;

final class LaravelBankImportArtifactStorage implements BankImportArtifactStorage
{
    public function storeImmutable(string $storageKey, string $bytes): bool
    {
        $disk = Storage::disk('bank_imports');
        if ($disk->exists($storageKey)) {
            return hash_equals(hash('sha256', (string) $disk->get($storageKey)), hash('sha256', $bytes));
        }

        return $disk->put($storageKey, $bytes);
    }

    public function read(string $storageKey): ?string
    {
        $disk = Storage::disk('bank_imports');

        return $disk->exists($storageKey) ? (string) $disk->get($storageKey) : null;
    }

    public function exists(string $storageKey): bool
    {
        return Storage::disk('bank_imports')->exists($storageKey);
    }

    public function promoteToRetained(string $temporaryKey, string $retainedKey, string $expectedSha256): bool
    {
        $disk = Storage::disk('bank_imports');
        if (! str_starts_with($temporaryKey, 'quarantine/') || ! str_starts_with($retainedKey, 'retained/') || $disk->exists($retainedKey)) {
            return false;
        }
        $bytes = $this->read($temporaryKey);
        if ($bytes === null || ! hash_equals($expectedSha256, hash('sha256', $bytes)) || ! $disk->move($temporaryKey, $retainedKey)) {
            return false;
        }
        $retained = $this->read($retainedKey);
        if ($retained !== null && hash_equals($expectedSha256, hash('sha256', $retained))) {
            return true;
        }
        if (! $disk->exists($temporaryKey)) {
            $disk->move($retainedKey, $temporaryKey);
        }

        return false;
    }

    public function restoreToQuarantine(string $retainedKey, string $temporaryKey, string $expectedSha256): bool
    {
        $disk = Storage::disk('bank_imports');
        $bytes = $this->read($retainedKey);
        if (! str_starts_with($retainedKey, 'retained/') || ! str_starts_with($temporaryKey, 'quarantine/') || $bytes === null || ! hash_equals($expectedSha256, hash('sha256', $bytes)) || $disk->exists($temporaryKey)) {
            return false;
        }

        return $disk->move($retainedKey, $temporaryKey);
    }

    public function deleteTemporary(string $storageKey): void
    {
        if (str_starts_with($storageKey, 'quarantine/')) {
            Storage::disk('bank_imports')->delete($storageKey);
        }
    }
}

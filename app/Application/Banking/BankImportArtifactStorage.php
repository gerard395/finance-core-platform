<?php

declare(strict_types=1);

namespace App\Application\Banking;

interface BankImportArtifactStorage
{
    public function storeImmutable(string $storageKey, string $bytes): bool;

    public function read(string $storageKey): ?string;

    public function exists(string $storageKey): bool;

    public function promoteToRetained(string $temporaryKey, string $retainedKey, string $expectedSha256): bool;

    public function deleteTemporary(string $storageKey): void;
}

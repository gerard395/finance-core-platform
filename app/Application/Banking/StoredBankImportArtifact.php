<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\OriginalFileHash;

final readonly class StoredBankImportArtifact
{
    public function __construct(public string $storageKey, public OriginalFileHash $hash, public int $byteSize) {}
}

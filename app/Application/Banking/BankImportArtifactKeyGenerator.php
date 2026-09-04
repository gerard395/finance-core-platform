<?php

declare(strict_types=1);

namespace App\Application\Banking;

interface BankImportArtifactKeyGenerator
{
    public function temporaryKey(): string;

    public function retainedKey(string $sha256): string;
}

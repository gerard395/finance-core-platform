<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportArtifactKeyGenerator;
use Illuminate\Support\Str;

final class LaravelBankImportArtifactKeyGenerator implements BankImportArtifactKeyGenerator
{
    public function temporaryKey(): string
    {
        return 'quarantine/'.Str::uuid()->toString().'.xml';
    }

    public function retainedKey(string $sha256): string
    {
        return 'retained/'.substr($sha256, 0, 2).'/'.Str::uuid()->toString().'.xml';
    }
}

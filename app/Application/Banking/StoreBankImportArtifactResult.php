<?php

declare(strict_types=1);

namespace App\Application\Banking;

final readonly class StoreBankImportArtifactResult
{
    public function __construct(public StoreBankImportArtifactStatus $status, public ?StoredBankImportArtifact $artifact = null) {}
}

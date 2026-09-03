<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportSourceIdentityGenerator;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankImportSourceIdentityGenerator implements BankImportSourceIdentityGenerator
{
    public function batchId(): BankImportBatchId
    {
        return new BankImportBatchId($this->uuid());
    }

    public function statementId(): BankStatementId
    {
        return new BankStatementId($this->uuid());
    }

    public function entryId(): BankStatementEntryId
    {
        return new BankStatementEntryId($this->uuid());
    }

    private function uuid(): Uuid
    {
        return new Uuid((string) Str::uuid());
    }
}

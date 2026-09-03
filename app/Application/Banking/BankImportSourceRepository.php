<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankImportBatch;
use App\Domain\Banking\ValueObjects\BankImportBatchId;

interface BankImportSourceRepository
{
    public function find(AdministrationId $administrationId, BankImportBatchId $id): ?BankImportBatch;

    public function insert(BankImportBatch $batch): bool;
}

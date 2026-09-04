<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankImportBatch;
use App\Domain\Banking\ValueObjects\BankImportBatchId;

interface BankImportSourceRepository
{
    /** @return list<BankImportBatch> */
    public function list(AdministrationId $administrationId): array;

    public function find(AdministrationId $administrationId, BankImportBatchId $id): ?BankImportBatch;

    public function conflict(BankImportBatch $batch): ?ConfirmBankImportStatus;

    public function insert(BankImportBatch $batch): bool;
}

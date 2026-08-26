<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\ValueObjects\BankTransactionId;

interface BankTransactionRepository
{
    public function save(BankTransaction $transaction): void;

    public function find(AdministrationId $administrationId, BankTransactionId $id, bool $forUpdate = false): ?BankTransaction;

    /** @return list<BankTransaction> */
    public function list(AdministrationId $administrationId): array;
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransactionReversal;
use App\Domain\Banking\ValueObjects\BankTransactionId;

interface BankTransactionReversalRepository
{
    public function findByOriginal(AdministrationId $administrationId, BankTransactionId $bankTransactionId, bool $forUpdate = false): ?BankTransactionReversal;

    public function appendReversal(BankTransactionReversal $reversal): bool;
}

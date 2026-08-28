<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

interface BankTransactionReversalSourceReader
{
    public function read(AdministrationId $administrationId, BankTransactionId $bankTransactionId, bool $forUpdate = false): ?BankTransactionReversalSource;
}

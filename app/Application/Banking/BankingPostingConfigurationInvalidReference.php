<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankingPostingConfigurationInvalidReference
{
    case BankAccount;
    case BankJournal;
    case BankLedgerAccount;
}

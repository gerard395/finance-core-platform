<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

interface BankingPostingConfigurationReader
{
    public function read(AdministrationId $administrationId, AdministrationBankAccountId $bankAccountId): BankingPostingConfigurationReadResult;
}

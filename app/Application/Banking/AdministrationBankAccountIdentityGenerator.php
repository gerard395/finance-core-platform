<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

interface AdministrationBankAccountIdentityGenerator
{
    public function next(): AdministrationBankAccountId;
}

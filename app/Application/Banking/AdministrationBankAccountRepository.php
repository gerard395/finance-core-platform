<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

interface AdministrationBankAccountRepository
{
    /** @return list<AdministrationBankAccount> */
    public function findForAdministration(AdministrationId $administrationId): array;

    public function find(AdministrationId $administrationId, AdministrationBankAccountId $id): ?AdministrationBankAccount;

    public function save(AdministrationBankAccount $account): AdministrationBankAccountWriteResult;
}

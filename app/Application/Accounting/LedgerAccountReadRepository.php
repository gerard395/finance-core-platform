<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface LedgerAccountReadRepository
{
    /** @return list<LedgerAccount> */
    public function findForAdministration(AdministrationId $administrationId): array;
}

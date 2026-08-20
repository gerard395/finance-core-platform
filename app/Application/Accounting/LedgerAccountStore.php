<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface LedgerAccountStore
{
    public function save(AdministrationId $administrationId, LedgerAccount $ledgerAccount): void;
}

<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface OtherContraAccountPolicy
{
    public function isAllowed(AdministrationId $administrationId, LedgerAccountId $accountId): bool;
}

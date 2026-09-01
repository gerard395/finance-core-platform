<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface AccountingPeriodHistoryReadRepository
{
    public function get(AdministrationId $administrationId, AccountingPeriodId $periodId): ?AccountingPeriodHistoryReadModel;
}

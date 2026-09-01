<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\AccountingPeriodId;

interface AccountingPeriodPlanIdentityGenerator
{
    public function periodId(): AccountingPeriodId;
}

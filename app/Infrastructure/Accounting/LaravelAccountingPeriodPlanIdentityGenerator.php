<?php

declare(strict_types=1);

namespace App\Infrastructure\Accounting;

use App\Application\Accounting\AccountingPeriodPlanIdentityGenerator;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelAccountingPeriodPlanIdentityGenerator implements AccountingPeriodPlanIdentityGenerator
{
    public function periodId(): AccountingPeriodId
    {
        return new AccountingPeriodId(new Uuid((string) Str::uuid()));
    }
}

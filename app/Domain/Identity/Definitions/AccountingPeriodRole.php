<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum AccountingPeriodRole: string
{
    case Manager = 'ACCOUNTING_PERIOD_MANAGER';
    case Reopener = 'ACCOUNTING_PERIOD_REOPENER';

    public function id(): RoleId
    {
        return new RoleId(new Uuid(match ($this) {
            self::Manager => 'a9020000-0000-4000-8000-000000000001',self::Reopener => 'a9020000-0000-4000-8000-000000000002'
        }));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName(match ($this) {
            self::Manager => 'Accounting Period Manager',self::Reopener => 'Accounting Period Reopener'
        });
    }

    public function permissions(): array
    {
        return match ($this) {
            self::Manager => [AccountingPeriodPermission::View, AccountingPeriodPermission::Manage, AccountingPeriodPermission::Close],self::Reopener => [AccountingPeriodPermission::View, AccountingPeriodPermission::Reopen]
        };
    }
}

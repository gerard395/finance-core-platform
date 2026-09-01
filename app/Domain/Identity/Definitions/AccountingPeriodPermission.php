<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum AccountingPeriodPermission: string
{
    case View = 'ACCOUNTING.PERIODS_VIEW';
    case Manage = 'ACCOUNTING.PERIODS_MANAGE';
    case Close = 'ACCOUNTING.PERIODS_CLOSE';
    case Reopen = 'ACCOUNTING.PERIODS_REOPEN';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid(match ($this) {
            self::View => 'a9010000-0000-4000-8000-000000000001',self::Manage => 'a9010000-0000-4000-8000-000000000002',self::Close => 'a9010000-0000-4000-8000-000000000003',self::Reopen => 'a9010000-0000-4000-8000-000000000004'
        }));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName(match ($this) {
            self::View => 'View Accounting Periods',self::Manage => 'Manage Accounting Periods',self::Close => 'Close Accounting Periods',self::Reopen => 'Reopen Accounting Periods'
        });
    }
}

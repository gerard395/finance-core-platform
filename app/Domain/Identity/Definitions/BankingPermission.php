<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum BankingPermission: string
{
    case View = 'BANKING.VIEW';
    case ManagePayments = 'BANKING.PAYMENTS_MANAGE';
    case PostPayments = 'BANKING.PAYMENTS_POST';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid(match ($this) {
            self::View => 'b2010000-0000-4000-8000-000000000001',
            self::ManagePayments => 'b2010000-0000-4000-8000-000000000002',
            self::PostPayments => 'b2010000-0000-4000-8000-000000000003',
        }));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName(match ($this) {
            self::View => 'View Banking',
            self::ManagePayments => 'Manage Manual Bank Payments',
            self::PostPayments => 'Post Bank Payments',
        });
    }
}

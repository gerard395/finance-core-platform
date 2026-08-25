<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum PurchasingRole: string
{
    case Manager = 'PURCHASING_MANAGER';
    case Poster = 'PURCHASING_POSTER';

    public function id(): RoleId
    {
        return new RoleId(new Uuid(match ($this) {
            self::Manager => '8c0eb3c2-2c80-4960-85bd-8649508cba83',
            self::Poster => '09544538-b049-4cf5-a691-8b88427f7b31',
        }));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName(match ($this) {
            self::Manager => 'Purchasing Manager',
            self::Poster => 'Purchasing Poster',
        });
    }

    /** @return list<PurchasingPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Manager => [PurchasingPermission::View, PurchasingPermission::ManageInvoiceDrafts, PurchasingPermission::FinalizeInvoices],
            self::Poster => [PurchasingPermission::View, PurchasingPermission::PostInvoices],
        };
    }
}

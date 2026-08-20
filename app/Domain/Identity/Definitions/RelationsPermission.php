<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum RelationsPermission: string
{
    case View = 'RELATIONS.VIEW';
    case Create = 'RELATIONS.CREATE';
    case Update = 'RELATIONS.UPDATE';
    case ClassifyCustomer = 'RELATIONS.CLASSIFY_CUSTOMER';
    case ClassifySupplier = 'RELATIONS.CLASSIFY_SUPPLIER';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid(match ($this) {
            self::View => '1a368b41-6ce7-4b79-bd69-61d2c4ce3fec',
            self::Create => '7e0470e3-d2d5-43d6-8a2b-d12f285e8675',
            self::Update => 'e40a3cef-6869-40fc-8e61-81ce1d8513f0',
            self::ClassifyCustomer => '920f64fe-eca1-4c54-9371-17c0e3e1af70',
            self::ClassifySupplier => '39e8392e-8cf8-4134-b709-3285550264c4',
        }));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName(match ($this) {
            self::View => 'View Relations',
            self::Create => 'Create Relation',
            self::Update => 'Change Relation',
            self::ClassifyCustomer => 'Manage Customer Classification',
            self::ClassifySupplier => 'Manage Supplier Classification',
        });
    }
}

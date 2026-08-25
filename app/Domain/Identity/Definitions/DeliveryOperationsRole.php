<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum DeliveryOperationsRole: string
{
    case Operator = 'DELIVERY_OPERATOR';

    public function id(): RoleId
    {
        return new RoleId(new Uuid('a341a9c4-c767-488e-8ba8-441d87ca3bf9'));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName('Delivery Operator');
    }

    /** @return list<DeliveryOperationsPermission> */
    public function permissions(): array
    {
        return [DeliveryOperationsPermission::ResolveUnknownOutcome];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum DeliveryOperationsPermission: string
{
    case ResolveUnknownOutcome = 'DELIVERY.OUTCOME_RESOLVE';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid('6f0c9ed8-1db2-4ae3-9cc4-df91c813bed4'));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName('Resolve Ambiguous Delivery Outcomes');
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;

interface DeliveryRequestStore
{
    public function createWithInitialOutbox(DeliveryRequest $request): CreateDeliveryRequestResult;

    public function find(AdministrationId $administrationId, DeliveryRequestId $requestId): ?DeliveryRequest;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\DeliveryRequest;

final readonly class CreateDeliveryRequestResult
{
    public function __construct(public CreateDeliveryRequestStatus $status, public ?DeliveryRequest $request = null) {}
}

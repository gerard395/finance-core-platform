<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesDocumentDeliveryInfrastructureReadiness
{
    public function check(): DeliveryInfrastructureReadiness;
}

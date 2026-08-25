<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface DeliveryWorkerHeartbeatStore
{
    public function beat(string $workerIdentity, ?string $release = null): void;
}

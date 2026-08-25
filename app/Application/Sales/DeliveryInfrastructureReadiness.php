<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class DeliveryInfrastructureReadiness
{
    /** @param array<string, int> $counters */
    public function __construct(
        public DeliveryInfrastructureReadinessStatus $status,
        public string $queueBackend,
        public string $queueName,
        public ?int $heartbeatAgeSeconds,
        public array $counters,
    ) {}
}

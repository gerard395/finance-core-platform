<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class SalesDocumentDeliveryHistory
{
    /** @param list<array<string, mixed>> $requests @param list<array<string, mixed>> $attempts @param array<string, array<string, mixed>> $resolutions */
    public function __construct(public array $requests, public array $attempts, public array $resolutions = []) {}
}

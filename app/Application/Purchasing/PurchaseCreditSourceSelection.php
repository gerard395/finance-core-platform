<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

final readonly class PurchaseCreditSourceSelection
{
    /** @param array<string, bool> $claimedLines */
    public function __construct(public PurchaseCreditSource $source, public array $claimedLines) {}
}

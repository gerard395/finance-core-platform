<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface QuotationDeliveryLifecycleCandidates
{
    /** @return list<QuotationDeliveryLifecycleCandidate> */
    public function pending(): array;
}

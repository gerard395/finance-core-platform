<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\QuotationId;

final readonly class QuotationDeliveryLifecycleCandidate
{
    public function __construct(public AdministrationId $administrationId, public QuotationId $quotationId) {}
}

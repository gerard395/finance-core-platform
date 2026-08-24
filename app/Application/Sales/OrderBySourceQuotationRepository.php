<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\ValueObjects\QuotationId;

interface OrderBySourceQuotationRepository
{
    public function findBySourceQuotation(AdministrationId $administrationId, QuotationId $quotationId): ?Order;
}

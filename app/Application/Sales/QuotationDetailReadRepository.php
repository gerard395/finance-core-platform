<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\QuotationId;

interface QuotationDetailReadRepository
{
    public function find(AdministrationId $administrationId, QuotationId $quotationId): ?QuotationDetail;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\ValueObjects\QuotationId;

interface QuotationReadRepository
{
    public function findForAdministration(AdministrationId $administrationId, QuotationId $quotationId): ?Quotation;
}

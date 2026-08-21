<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Quotation;

interface QuotationUpdater
{
    public function update(AdministrationId $administrationId, Quotation $quotation): QuotationWriteResult;
}

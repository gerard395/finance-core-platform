<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Quotation;

interface QuotationCreator
{
    public function create(AdministrationId $administrationId, Quotation $quotation): QuotationWriteResult;
}

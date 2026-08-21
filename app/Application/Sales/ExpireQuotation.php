<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\QuotationId;

final readonly class ExpireQuotation
{
    public function __construct(private QuotationMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, QuotationId $quotationId): QuotationWriteResult
    {
        return $this->mutations->mutate($administrationId, $quotationId, static fn ($quotation) => $quotation->expire());
    }
}

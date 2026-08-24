<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\ValueObjects\QuotationId;

final readonly class UpdateQuotationLine
{
    public function __construct(private QuotationMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, QuotationId $quotationId, QuotationLine $line): QuotationWriteResult
    {
        return $this->mutations->mutate($administrationId, $quotationId, static fn ($quotation) => $quotation->updateLine($line));
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use DomainException;

final readonly class RemoveQuotationLine
{
    public function __construct(private QuotationMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, QuotationId $quotationId, QuotationLineId $lineId): QuotationWriteResult
    {
        return $this->mutations->mutate($administrationId, $quotationId, static function ($quotation) use ($lineId): void {
            if (! $quotation->hasLine($lineId)) {
                throw new DomainException('Quotation line does not exist.');
            }
            $quotation->removeLine($lineId);
        });
    }
}

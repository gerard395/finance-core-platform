<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetSalesDocumentDeliveryHistory
{
    public function __construct(private SalesDocumentDeliveryHistoryReader $history) {}

    public function execute(AdministrationId $administrationId, SalesDocumentSource $source): SalesDocumentDeliveryHistory
    {
        return $this->history->history($administrationId, $source);
    }
}

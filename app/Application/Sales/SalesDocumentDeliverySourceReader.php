<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface SalesDocumentDeliverySourceReader
{
    public function read(AdministrationId $administrationId, SalesDocumentSource $source): ?SalesDocumentDeliverySource;
}

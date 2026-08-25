<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\RelationId;

final readonly class SalesDocumentDeliverySource
{
    public function __construct(public SalesDocumentSource $source, public string $documentNumber, public RelationId $relationId, public string $customerName, public string $status, public bool $hasDocumentAddress) {}
}

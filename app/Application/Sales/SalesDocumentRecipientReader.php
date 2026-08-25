<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

interface SalesDocumentRecipientReader
{
    public function read(AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose): SalesDocumentRecipient;
}

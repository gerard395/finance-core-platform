<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class ClearPreferredSalesDocumentRecipient
{
    public function __construct(private SalesDocumentRecipientPreferenceStore $preferences) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose): bool
    {
        return $this->preferences->clear($administrationId, $relationId, $purpose);
    }
}

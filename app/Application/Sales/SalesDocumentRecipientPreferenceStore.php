<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesDocumentRecipientPreferenceId;

interface SalesDocumentRecipientPreferenceStore
{
    public function set(SalesDocumentRecipientPreferenceId $id, AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose, ContactId $contactId): SetPreferredSalesDocumentRecipientResult;

    public function clear(AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose): bool;
}

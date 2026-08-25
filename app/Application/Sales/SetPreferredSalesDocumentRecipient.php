<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesDocumentRecipientPreferenceId;

final readonly class SetPreferredSalesDocumentRecipient
{
    public function __construct(private SalesDocumentRecipientPreferenceStore $preferences) {}

    public function execute(SalesDocumentRecipientPreferenceId $id, AdministrationId $administrationId, RelationId $relationId, SalesDocumentRecipientPurpose $purpose, ContactId $contactId): SetPreferredSalesDocumentRecipientResult
    {
        return $this->preferences->set($id, $administrationId, $relationId, $purpose, $contactId);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxLegId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;

interface PurchaseInvoicePostingIdentityGenerator
{
    public function journalEntryId(): JournalEntryId;

    public function journalEntryLineId(): JournalEntryLineId;

    public function taxPostingId(): TaxPostingId;

    public function taxLegId(): TaxLegId;

    public function taxTreatmentGroupId(): TaxTreatmentGroupId;

    public function openItemId(): OpenItemId;
}

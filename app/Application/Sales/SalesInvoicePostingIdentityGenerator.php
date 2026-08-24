<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;

interface SalesInvoicePostingIdentityGenerator
{
    public function journalEntryId(): JournalEntryId;

    public function journalEntryLineId(): JournalEntryLineId;

    public function taxPostingId(): TaxPostingId;

    public function openItemId(): OpenItemId;
}

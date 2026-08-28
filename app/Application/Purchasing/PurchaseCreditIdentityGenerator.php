<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditSourceLineClaimId;

interface PurchaseCreditIdentityGenerator
{
    public function creditId(): PurchaseCreditInvoiceId;

    public function lineId(): PurchaseCreditInvoiceLineId;

    public function journalEntryId(): JournalEntryId;

    public function journalEntryLineId(): JournalEntryLineId;

    public function taxPostingId(): TaxPostingId;

    public function openItemId(): OpenItemId;

    public function postingId(): PurchaseCreditPostingId;

    public function claimId(): PurchaseCreditSourceLineClaimId;
}

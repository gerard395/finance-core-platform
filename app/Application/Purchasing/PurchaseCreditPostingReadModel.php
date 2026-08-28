<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Finance\Money;

final readonly class PurchaseCreditPostingReadModel
{
    /** @param array<string, TaxPostingId> $reversalTaxPostingIdsByCreditLine */
    public function __construct(public PurchaseCreditInvoiceId $creditId, public PostingDate $postingDate, public JournalEntryId $journalEntryId, public OpenItemId $creditPayableOpenItemId, public Money $grossAmount, public PurchaseInvoiceId $sourceInvoiceId, public OpenItemId $sourcePayableOpenItemId, public array $reversalTaxPostingIdsByCreditLine, public bool $allSourceLinesClaimed) {}
}

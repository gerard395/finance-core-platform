<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;

final readonly class PurchasingCreditTaxLegReversalInput
{
    public function __construct(
        public TaxPosting $original,
        public LedgerAccountId $taxAccountId,
        public JournalEntryLineId $journalEntryLineId,
        public TaxPostingId $reversalTaxPostingId,
    ) {}
}

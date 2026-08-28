<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Shared\Finance\Money;

final readonly class PostPurchaseCreditInvoiceResult
{
    /** @param list<TaxPostingId> $taxPostingIds */
    public function __construct(
        public PostPurchaseCreditInvoiceStatus $status,
        public ?JournalEntryId $journalEntryId = null,
        public ?OpenItemId $openItemId = null,
        public array $taxPostingIds = [],
        public ?OpenItemId $sourceOpenItemId = null,
        public ?Money $matchedAmount = null,
        public ?Money $sourceRemainingAmount = null,
        public ?Money $creditRemainingAmount = null,
    ) {}
}

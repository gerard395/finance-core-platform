<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;

final readonly class PurchaseCreditHistoricalPosting
{
    /** @param array<string,LedgerAccountId|null> $vatAccounts */
    public function __construct(public JournalEntryId $sourceJournalEntryId, public JournalId $journalId, public OpenItem $sourcePayable, public array $vatAccounts) {}
}

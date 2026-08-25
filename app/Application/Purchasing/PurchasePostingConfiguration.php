<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class PurchasePostingConfiguration
{
    public function __construct(
        public AdministrationId $administrationId,
        public JournalId $purchaseJournalId,
        public LedgerAccountId $accountsPayableLedgerAccountId,
        public LedgerAccountId $inputVatLedgerAccountId,
    ) {}
}

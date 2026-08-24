<?php

declare(strict_types=1);

namespace App\Application\Development;

use App\Application\Sales\SalesPostingConfiguration;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;

final readonly class DevelopmentAccountingMasterDataProvisioningResult
{
    public function __construct(
        public Journal $salesJournal,
        public LedgerAccount $accountsReceivable,
        public LedgerAccount $revenue,
        public LedgerAccount $outputVat,
        public SalesPostingConfiguration $salesPostingConfiguration,
    ) {}
}

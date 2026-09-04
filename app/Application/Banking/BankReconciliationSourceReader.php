<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface BankReconciliationSourceReader
{
    /** @return list<BankReconciliationSourceItem> */
    public function list(AdministrationId $administrationId, BankReconciliationWorklistFilter $filter): array;
}

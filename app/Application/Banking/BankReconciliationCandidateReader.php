<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface BankReconciliationCandidateReader
{
    /** @return list<BankReconciliationCandidate> */
    public function eligible(AdministrationId $administrationId, PostingDate $asOf): array;
}

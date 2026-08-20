<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface JournalEntryReadRepository
{
    /** @return list<JournalEntry> */
    public function findPostedForAdministrationAndPeriod(
        AdministrationId $administrationId,
        PostingDate $startDate,
        PostingDate $endDate,
    ): array;
}

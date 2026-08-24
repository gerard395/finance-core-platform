<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface JournalReadRepository
{
    public function findByIdForAdministration(AdministrationId $administrationId, JournalId $journalId): ?Journal;

    /** @return list<Journal> */
    public function findForAdministration(AdministrationId $administrationId): array;
}

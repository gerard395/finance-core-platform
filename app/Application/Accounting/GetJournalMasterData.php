<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetJournalMasterData
{
    public function __construct(private JournalReadRepository $journals) {}

    /** @return list<Journal> */
    public function list(AdministrationId $administrationId): array
    {
        return $this->journals->findForAdministration($administrationId);
    }

    public function find(AdministrationId $administrationId, JournalId $journalId): ?Journal
    {
        return $this->journals->findByIdForAdministration($administrationId, $journalId);
    }
}

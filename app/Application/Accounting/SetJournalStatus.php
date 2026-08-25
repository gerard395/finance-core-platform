<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;

final readonly class SetJournalStatus
{
    public function __construct(private JournalReadRepository $reader, private JournalStore $store) {}

    public function execute(AdministrationId $administrationId, JournalId $journalId, JournalStatus $status): AccountingMasterDataWriteResult
    {
        $journal = $this->reader->findByIdForAdministration($administrationId, $journalId);
        if ($journal === null) {
            return AccountingMasterDataWriteResult::NotFound;
        }
        $status === JournalStatus::Active ? $journal->activate() : $journal->deactivate();
        try {
            $this->store->save($administrationId, $journal);
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

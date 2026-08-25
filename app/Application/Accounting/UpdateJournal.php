<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;
use InvalidArgumentException;

final readonly class UpdateJournal
{
    public function __construct(private JournalReadRepository $reader, private JournalStore $store) {}

    public function execute(AdministrationId $administrationId, JournalId $journalId, string $name): AccountingMasterDataWriteResult
    {
        $journal = $this->reader->findByIdForAdministration($administrationId, $journalId);
        if ($journal === null) {
            return AccountingMasterDataWriteResult::NotFound;
        }
        try {
            $journal->rename(new JournalName($name));
            $this->store->save($administrationId, $journal);
        } catch (InvalidArgumentException) {
            return AccountingMasterDataWriteResult::InvalidInput;
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

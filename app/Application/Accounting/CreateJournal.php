<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;
use InvalidArgumentException;

final readonly class CreateJournal
{
    public function __construct(private JournalReadRepository $reader, private JournalStore $store, private AccountingMasterDataIdentityGenerator $identities) {}

    public function execute(AdministrationId $administrationId, string $code, string $name, JournalType $type): AccountingMasterDataWriteResult
    {
        try {
            $code = new JournalCode($code);
            $name = new JournalName($name);
        } catch (InvalidArgumentException) {
            return AccountingMasterDataWriteResult::InvalidInput;
        }
        foreach ($this->reader->findForAdministration($administrationId) as $existing) {
            if ($existing->code()->equals($code)) {
                return AccountingMasterDataWriteResult::DuplicateCode;
            }
        }

        try {
            $this->store->save($administrationId, new Journal($this->identities->journalId(), $code, $name, $type, JournalStatus::Active));
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

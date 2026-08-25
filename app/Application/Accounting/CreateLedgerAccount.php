<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;
use InvalidArgumentException;

final readonly class CreateLedgerAccount
{
    public function __construct(private LedgerAccountReadRepository $reader, private LedgerAccountStore $store, private AccountingMasterDataIdentityGenerator $identities) {}

    public function execute(AdministrationId $administrationId, string $code, string $name, LedgerAccountType $type): AccountingMasterDataWriteResult
    {
        try {
            $code = new LedgerAccountCode($code);
            $name = new LedgerAccountName($name);
        } catch (InvalidArgumentException) {
            return AccountingMasterDataWriteResult::InvalidInput;
        }
        foreach ($this->reader->findForAdministration($administrationId) as $existing) {
            if ($existing->code()->equals($code)) {
                return AccountingMasterDataWriteResult::DuplicateCode;
            }
        }
        try {
            $this->store->save($administrationId, new LedgerAccount($this->identities->ledgerAccountId(), $code, $name, $type, LedgerAccountStatus::Active));
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

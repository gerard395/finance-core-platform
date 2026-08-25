<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;

final readonly class SetLedgerAccountStatus
{
    public function __construct(private GetLedgerAccountMasterData $reader, private LedgerAccountStore $store) {}

    public function execute(AdministrationId $administrationId, LedgerAccountId $accountId, LedgerAccountStatus $status): AccountingMasterDataWriteResult
    {
        $account = $this->reader->find($administrationId, $accountId);
        if ($account === null) {
            return AccountingMasterDataWriteResult::NotFound;
        }
        $status === LedgerAccountStatus::Active ? $account->activate() : $account->deactivate();
        try {
            $this->store->save($administrationId, $account);
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

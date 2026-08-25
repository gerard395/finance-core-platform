<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;
use InvalidArgumentException;

final readonly class UpdateLedgerAccount
{
    public function __construct(private GetLedgerAccountMasterData $reader, private LedgerAccountStore $store) {}

    public function execute(AdministrationId $administrationId, LedgerAccountId $accountId, string $name): AccountingMasterDataWriteResult
    {
        $account = $this->reader->find($administrationId, $accountId);
        if ($account === null) {
            return AccountingMasterDataWriteResult::NotFound;
        }
        try {
            $account->rename(new LedgerAccountName($name));
            $this->store->save($administrationId, $account);
        } catch (InvalidArgumentException) {
            return AccountingMasterDataWriteResult::InvalidInput;
        } catch (DomainException) {
            return AccountingMasterDataWriteResult::PersistenceConflict;
        }

        return AccountingMasterDataWriteResult::Success;
    }
}

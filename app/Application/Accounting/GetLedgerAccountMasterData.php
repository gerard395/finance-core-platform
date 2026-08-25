<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetLedgerAccountMasterData
{
    public function __construct(private LedgerAccountReadRepository $accounts) {}

    /** @return list<LedgerAccount> */
    public function list(AdministrationId $administrationId): array
    {
        return $this->accounts->findForAdministration($administrationId);
    }

    public function find(AdministrationId $administrationId, LedgerAccountId $ledgerAccountId): ?LedgerAccount
    {
        foreach ($this->accounts->findForAdministration($administrationId) as $account) {
            if ($account->id()->equals($ledgerAccountId)) {
                return $account;
            }
        }

        return null;
    }
}

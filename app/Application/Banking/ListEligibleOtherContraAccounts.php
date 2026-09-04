<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\LedgerAccountReadRepository;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class ListEligibleOtherContraAccounts
{
    public function __construct(private LedgerAccountReadRepository $accounts, private OtherContraAccountPolicy $policy) {}

    public function execute(AdministrationId $administrationId): array
    {
        return array_values(array_filter(
            $this->accounts->findForAdministration($administrationId),
            fn ($account): bool => $account->status() === LedgerAccountStatus::Active && $this->policy->isAllowed($administrationId, $account->id()),
        ));
    }
}

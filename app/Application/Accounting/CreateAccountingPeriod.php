<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\AccountingPeriod;

final readonly class CreateAccountingPeriod
{
    public function __construct(private TransactionManager $transactions, private BookYearRepository $years) {}

    public function execute(AccountingPeriod $period): AccountingPeriodMutationStatus
    {
        return $this->transactions->run(function () use ($period): AccountingPeriodMutationStatus {
            $this->years->lockAdministration($period->administrationId());
            $year = $this->years->find($period->administrationId(), $period->bookYearId(), true);
            if ($year === null) {
                return AccountingPeriodMutationStatus::NotFound;
            }

            try {
                $year->addPeriod($period);
            } catch (\DomainException) {
                return AccountingPeriodMutationStatus::IntegrityFailure;
            }

            return $this->years->savePeriod($period)
                ? AccountingPeriodMutationStatus::Success
                : AccountingPeriodMutationStatus::IntegrityFailure;
        });
    }
}

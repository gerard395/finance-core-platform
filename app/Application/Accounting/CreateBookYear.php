<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\BookYear;

final readonly class CreateBookYear
{
    public function __construct(private TransactionManager $transactions, private BookYearRepository $years) {}

    public function execute(BookYear $year): AccountingPeriodMutationStatus
    {
        return $this->transactions->run(function () use ($year) {
            $this->years->lockAdministration($year->administrationId());
            if ($this->years->overlaps($year->administrationId(), $year->startDate(), $year->endDate())) {
                return AccountingPeriodMutationStatus::IntegrityFailure;
            }

            return $this->years->save($year) ? AccountingPeriodMutationStatus::Success : AccountingPeriodMutationStatus::IntegrityFailure;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class UpdateBookYearLabel
{
    public function __construct(private BookYearRepository $years) {}

    public function execute(AdministrationId $a, BookYearId $id, string $label): AccountingPeriodMutationStatus
    {
        if (mb_strlen(trim($label)) > 100) {
            return AccountingPeriodMutationStatus::InvalidState;
        }

        return $this->years->updateLabel($a, $id, trim($label)) ? AccountingPeriodMutationStatus::Success : AccountingPeriodMutationStatus::NotFound;
    }
}

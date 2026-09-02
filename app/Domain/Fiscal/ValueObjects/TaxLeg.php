<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class TaxLeg
{
    public function __construct(
        public TaxLegId $id,
        public TaxTreatmentGroupId $groupId,
        public TaxLegRole $role,
        public TaxPostingDirection $direction,
        public Money $taxableBasis,
        public TaxRate $rate,
        public Money $amount,
        public TaxReportingClassification $reportingClassification,
        public TaxLedgerAccountRole $ledgerAccountRole,
    ) {
        if (! $taxableBasis->currency()->equals($amount->currency()) || $taxableBasis->isNegative() || $amount->isNegative()) {
            throw new InvalidArgumentException('Tax leg amounts must be non-negative and use one currency.');
        }
    }
}

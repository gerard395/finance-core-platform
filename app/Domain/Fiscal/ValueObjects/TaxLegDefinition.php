<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use InvalidArgumentException;

final readonly class TaxLegDefinition
{
    public function __construct(
        public TaxLegRole $role,
        public TaxPostingDirection $direction,
        public TaxReportingClassification $reportingClassification,
        public TaxLedgerAccountRole $ledgerAccountRole,
        public bool $emitWhenZero = false,
    ) {
        if ($role === TaxLegRole::VatPayable && ($direction !== TaxPostingDirection::Output || $ledgerAccountRole !== TaxLedgerAccountRole::VatPayableControl)) {
            throw new InvalidArgumentException('VAT payable legs require Output direction and VAT payable control account role.');
        }

        if ($role === TaxLegRole::VatDeductible && ($direction !== TaxPostingDirection::Input || $ledgerAccountRole !== TaxLedgerAccountRole::InputVatControl)) {
            throw new InvalidArgumentException('VAT deductible legs require Input direction and Input VAT control account role.');
        }
    }
}

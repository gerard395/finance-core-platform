<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Purchasing\ValueObjects\InternationalPurchaseSourceFacts;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;

final readonly class PurchaseInvoiceLineInput
{
    public function __construct(
        public LineDescription $description,
        public Quantity $quantity,
        public Money $unitPrice,
        public LedgerAccountId $ledgerAccountId,
        public TaxCodeId $taxCodeId,
        public bool $fullyDeductible,
        public ?DeductibilityBasisPoints $deductibility = null,
        public ?InternationalPurchaseSourceFacts $internationalSourceFacts = null,
    ) {}
}

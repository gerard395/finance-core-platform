<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\Entities\TaxCode;
use LogicException;

final readonly class SalesTaxCodeResolution
{
    private function __construct(
        private SalesTaxCodeResolutionStatus $status,
        private ?TaxCode $taxCode = null,
    ) {}

    public static function success(TaxCode $taxCode): self
    {
        return new self(SalesTaxCodeResolutionStatus::Success, $taxCode);
    }

    public static function failure(SalesTaxCodeResolutionStatus $status): self
    {
        if ($status === SalesTaxCodeResolutionStatus::Success) {
            throw new LogicException('A successful tax code resolution requires a TaxCode.');
        }

        return new self($status);
    }

    public function status(): SalesTaxCodeResolutionStatus
    {
        return $this->status;
    }

    public function taxCode(): ?TaxCode
    {
        return $this->taxCode;
    }
}

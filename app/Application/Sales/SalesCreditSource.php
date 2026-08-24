<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Sales\Entities\SalesInvoice;

final readonly class SalesCreditSource
{
    /** @param list<TaxPosting> $originalTaxPostings */
    public function __construct(private SalesCreditSourceStatus $status, private ?SalesInvoice $invoice = null, private array $originalTaxPostings = []) {}

    public function status(): SalesCreditSourceStatus
    {
        return $this->status;
    }

    public function invoice(): ?SalesInvoice
    {
        return $this->invoice;
    }

    /** @return list<TaxPosting> */
    public function originalTaxPostings(): array
    {
        return $this->originalTaxPostings;
    }
}

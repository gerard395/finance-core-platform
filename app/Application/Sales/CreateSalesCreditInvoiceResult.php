<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

final readonly class CreateSalesCreditInvoiceResult
{
    public function __construct(private SalesCreditInvoiceWriteResult $status, private ?SalesCreditInvoiceId $creditInvoiceId = null) {}

    public static function success(SalesCreditInvoiceId $id): self
    {
        return new self(SalesCreditInvoiceWriteResult::Success, $id);
    }

    public function status(): SalesCreditInvoiceWriteResult
    {
        return $this->status;
    }

    public function creditInvoiceId(): ?SalesCreditInvoiceId
    {
        return $this->creditInvoiceId;
    }
}

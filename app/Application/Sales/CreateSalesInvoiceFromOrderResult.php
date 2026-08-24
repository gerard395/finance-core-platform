<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class CreateSalesInvoiceFromOrderResult
{
    private function __construct(private CreateSalesInvoiceFromOrderStatus $status, private ?SalesInvoiceId $salesInvoiceId = null) {}

    public static function success(SalesInvoiceId $id): self
    {
        return new self(CreateSalesInvoiceFromOrderStatus::Success, $id);
    }

    public static function forStatus(CreateSalesInvoiceFromOrderStatus $status, ?SalesInvoiceId $id = null): self
    {
        return new self($status, $id);
    }

    public function status(): CreateSalesInvoiceFromOrderStatus
    {
        return $this->status;
    }

    public function salesInvoiceId(): ?SalesInvoiceId
    {
        return $this->salesInvoiceId;
    }
}

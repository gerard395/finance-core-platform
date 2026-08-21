<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class UpdateSalesInvoiceLine
{
    public function __construct(private SalesInvoiceLineMutationService $lines) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineInput $line): SalesInvoiceWriteResult
    {
        return $this->lines->update($administrationId, $invoiceId, $line);
    }
}

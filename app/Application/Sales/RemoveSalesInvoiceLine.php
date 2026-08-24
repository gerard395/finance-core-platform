<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use DomainException;

final readonly class RemoveSalesInvoiceLine
{
    public function __construct(private SalesInvoiceMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineId $lineId): SalesInvoiceWriteResult
    {
        return $this->mutations->mutate($administrationId, $invoiceId, static function ($invoice) use ($lineId): ?SalesInvoiceWriteResult {
            if (! $invoice->hasLine($lineId)) {
                throw new DomainException('Sales invoice line does not exist.');
            }
            $invoice->removeLine($lineId);

            return null;
        });
    }
}

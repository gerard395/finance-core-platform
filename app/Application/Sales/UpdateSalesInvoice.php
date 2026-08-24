<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;

final readonly class UpdateSalesInvoice
{
    public function __construct(private SalesInvoiceMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId, DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate): SalesInvoiceWriteResult
    {
        return $this->mutations->mutate($administrationId, $invoiceId, static function ($invoice) use ($invoiceDate, $dueDate): ?SalesInvoiceWriteResult {
            $invoice->changeDates($invoiceDate, $dueDate);

            return null;
        });
    }
}

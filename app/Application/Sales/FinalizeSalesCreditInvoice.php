<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

final readonly class FinalizeSalesCreditInvoice
{
    public function __construct(private SalesCreditSourceReader $sources, private SalesCreditInvoiceConsistency $consistency, private SalesCreditInvoiceMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, SalesCreditInvoiceId $id): SalesCreditInvoiceWriteResult
    {
        return $this->mutations->mutate($administrationId, $id, function ($credit) use ($administrationId): ?SalesCreditInvoiceWriteResult {
            $source = $this->sources->read($administrationId, $credit->sourceInvoiceId(), $credit->id());
            if (! $this->consistency->matches($credit, $source)) {
                return SalesCreditInvoiceWriteResult::ReversalSourceInvalid;
            }
            $credit->finalize();

            return null;
        });
    }
}

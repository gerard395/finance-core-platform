<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

final readonly class CancelSalesCreditInvoice
{
    public function __construct(private SalesCreditInvoiceMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, SalesCreditInvoiceId $id): SalesCreditInvoiceWriteResult
    {
        return $this->mutations->mutate($administrationId, $id, static function ($credit): ?SalesCreditInvoiceWriteResult {
            $credit->cancel();

            return null;
        });
    }
}

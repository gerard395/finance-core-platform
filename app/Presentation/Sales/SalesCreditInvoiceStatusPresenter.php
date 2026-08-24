<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;

final class SalesCreditInvoiceStatusPresenter
{
    public static function label(SalesCreditInvoiceStatus $status): string
    {
        return match ($status) {
            SalesCreditInvoiceStatus::Draft => 'Concept',
            SalesCreditInvoiceStatus::Finalized => 'Definitief',
            SalesCreditInvoiceStatus::Posted => 'Geboekt',
            SalesCreditInvoiceStatus::Cancelled => 'Geannuleerd',
        };
    }
}

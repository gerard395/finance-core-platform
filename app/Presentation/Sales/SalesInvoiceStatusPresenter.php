<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Domain\Sales\Enums\SalesInvoiceStatus;

final class SalesInvoiceStatusPresenter
{
    public static function label(SalesInvoiceStatus $status): string
    {
        return match ($status) {
            SalesInvoiceStatus::Draft => 'Concept',
            SalesInvoiceStatus::Finalized => 'Definitief',
            SalesInvoiceStatus::Posted => 'Geboekt',
            SalesInvoiceStatus::Paid => 'Betaald',
            SalesInvoiceStatus::Cancelled => 'Geannuleerd',
        };
    }
}

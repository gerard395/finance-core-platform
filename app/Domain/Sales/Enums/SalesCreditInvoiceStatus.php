<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum SalesCreditInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}

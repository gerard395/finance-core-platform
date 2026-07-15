<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum SalesInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}

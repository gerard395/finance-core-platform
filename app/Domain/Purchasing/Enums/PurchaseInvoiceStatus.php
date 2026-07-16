<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}

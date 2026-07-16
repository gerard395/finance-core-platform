<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Enums;

enum PurchaseCreditInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}

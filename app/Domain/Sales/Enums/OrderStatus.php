<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyInvoiced = 'partially_invoiced';
    case FullyInvoiced = 'fully_invoiced';
    case Cancelled = 'cancelled';
}

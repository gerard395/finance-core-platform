<?php

declare(strict_types=1);

namespace App\Domain\Relations\Enums;

enum AddressType: string
{
    case Visiting = 'visiting';
    case Postal = 'postal';
    case Invoice = 'invoice';
    case Delivery = 'delivery';
    case Quotation = 'quotation';
}

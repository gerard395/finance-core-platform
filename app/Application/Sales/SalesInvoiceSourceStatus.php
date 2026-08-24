<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoiceSourceStatus
{
    case Posted;
    case NotFound;
    case NotPosted;
}

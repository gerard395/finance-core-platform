<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesCreditInvoicePostingAppendResult
{
    case Appended;
    case AlreadyExists;
}

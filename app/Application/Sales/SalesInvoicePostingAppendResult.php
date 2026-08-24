<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesInvoicePostingAppendResult
{
    case Appended;
    case AlreadyExists;
}

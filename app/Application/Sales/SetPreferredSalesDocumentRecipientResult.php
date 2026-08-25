<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SetPreferredSalesDocumentRecipientResult
{
    case Success;
    case InvalidContact;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesDocumentRecipientStatus
{
    case Success;
    case Missing;
    case Invalid;
}

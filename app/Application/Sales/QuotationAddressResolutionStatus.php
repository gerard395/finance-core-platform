<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum QuotationAddressResolutionStatus
{
    case Success;
    case NotFound;
    case Inactive;
    case InvalidPurpose;
}

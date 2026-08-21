<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesTaxCodeResolutionStatus
{
    case Success;
    case NotFound;
    case Inactive;
    case WrongDirection;
}

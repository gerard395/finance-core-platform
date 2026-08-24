<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesCustomerContextStatus
{
    case Success;
    case NotFound;
    case InactiveCustomer;
    case MissingInvoiceAddress;
}

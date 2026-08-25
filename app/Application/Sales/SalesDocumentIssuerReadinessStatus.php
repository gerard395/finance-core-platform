<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesDocumentIssuerReadinessStatus
{
    case Success;
    case MissingIssuerName;
    case MissingAddress;
    case MissingRegistrationNumber;
    case MissingPaymentData;
}

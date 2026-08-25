<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum PrepareSalesDocumentArtifactStatus
{
    case Success;
    case Reused;
    case NotFound;
    case MissingDocumentAddress;
    case MissingIssuerData;
    case MissingPaymentData;
    case InvalidSource;
    case IntegrityFailure;
    case RenderingFailure;
    case StorageFailure;
    case PersistenceFailure;
}

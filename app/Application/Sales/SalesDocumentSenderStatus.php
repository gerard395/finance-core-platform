<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesDocumentSenderStatus
{
    case Success;
    case MissingFromName;
    case MissingFromEmail;
    case InvalidFromEmail;
    case InvalidReplyTo;
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface SalesDocumentIssuerReader
{
    public function readIssuer(AdministrationId $administrationId): ?SalesDocumentIssuer;
}

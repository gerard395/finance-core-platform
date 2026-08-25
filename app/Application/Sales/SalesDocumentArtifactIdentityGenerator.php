<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\ArtifactId;

interface SalesDocumentArtifactIdentityGenerator
{
    public function next(): ArtifactId;
}

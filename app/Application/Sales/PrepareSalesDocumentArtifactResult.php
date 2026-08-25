<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\DocumentArtifact;

final readonly class PrepareSalesDocumentArtifactResult
{
    public function __construct(public PrepareSalesDocumentArtifactStatus $status, public ?DocumentArtifact $artifact = null) {}
}

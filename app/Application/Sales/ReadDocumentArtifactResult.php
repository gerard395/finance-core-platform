<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\DocumentArtifact;

final readonly class ReadDocumentArtifactResult
{
    public function __construct(public ?DocumentArtifact $artifact, public ?string $bytes, public bool $integrityValid) {}
}

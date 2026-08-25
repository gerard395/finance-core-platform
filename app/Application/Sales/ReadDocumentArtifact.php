<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\ArtifactId;

final readonly class ReadDocumentArtifact
{
    public function __construct(private DocumentArtifactRepository $artifacts, private DocumentArtifactStorage $storage) {}

    public function execute(AdministrationId $administrationId, ArtifactId $artifactId): ReadDocumentArtifactResult
    {
        $artifact = $this->artifacts->find($administrationId, $artifactId);
        if ($artifact === null) {
            return new ReadDocumentArtifactResult(null, null, false);
        }
        $bytes = $this->storage->read($artifact->storageKey);

        return new ReadDocumentArtifactResult($artifact, $bytes, $bytes !== null && strlen($bytes) === $artifact->byteSize && hash('sha256', $bytes) === $artifact->sha256);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\DocumentArtifact;
use App\Domain\Sales\ValueObjects\ArtifactId;

interface DocumentArtifactRepository
{
    public function findBySourceFingerprint(AdministrationId $administrationId, SalesDocumentSource $source, string $fingerprint): ?DocumentArtifact;

    public function find(AdministrationId $administrationId, ArtifactId $artifactId): ?DocumentArtifact;

    public function nextVersion(AdministrationId $administrationId, SalesDocumentSource $source): int;

    public function persist(DocumentArtifact $artifact): DocumentArtifact;
}

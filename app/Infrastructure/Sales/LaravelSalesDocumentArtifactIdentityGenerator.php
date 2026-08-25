<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesDocumentArtifactIdentityGenerator;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelSalesDocumentArtifactIdentityGenerator implements SalesDocumentArtifactIdentityGenerator
{
    public function next(): ArtifactId
    {
        return new ArtifactId(new Uuid(Str::uuid()->toString()));
    }
}

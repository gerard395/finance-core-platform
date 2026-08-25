<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface DocumentArtifactFailureReporter
{
    public function report(string $stage, AdministrationId $administrationId, ?string $storageKey = null): void;
}

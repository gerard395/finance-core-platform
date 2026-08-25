<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DocumentArtifactFailureReporter;
use App\Domain\Administration\ValueObjects\AdministrationId;
use Illuminate\Support\Facades\Log;

final class LaravelDocumentArtifactFailureReporter implements DocumentArtifactFailureReporter
{
    public function report(string $stage, AdministrationId $administrationId, ?string $storageKey = null): void
    {
        Log::warning('Sales document artifact operation failed.', array_filter([
            'stage' => $stage,
            'administration_id' => $administrationId->toString(),
            'storage_key' => $storageKey,
        ]));
    }
}

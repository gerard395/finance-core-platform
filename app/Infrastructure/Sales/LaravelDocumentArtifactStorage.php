<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DocumentArtifactStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LaravelDocumentArtifactStorage implements DocumentArtifactStorage
{
    public function store(string $storageKey, string $bytes): void
    {
        if (! Storage::disk('sales_documents')->put($storageKey, $bytes)) {
            throw new RuntimeException('Private artifact storage failed.');
        }
    }

    public function read(string $storageKey): ?string
    {
        if (! $this->exists($storageKey)) {
            return null;
        }
        $bytes = Storage::disk('sales_documents')->get($storageKey);

        return is_string($bytes) ? $bytes : null;
    }

    public function exists(string $storageKey): bool
    {
        return Storage::disk('sales_documents')->exists($storageKey);
    }

    public function deleteOrphan(string $storageKey): void
    {
        Storage::disk('sales_documents')->delete($storageKey);
    }
}

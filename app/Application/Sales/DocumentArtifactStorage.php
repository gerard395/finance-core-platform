<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface DocumentArtifactStorage
{
    public function store(string $storageKey, string $bytes): void;

    public function read(string $storageKey): ?string;

    public function exists(string $storageKey): bool;

    public function deleteOrphan(string $storageKey): void;
}

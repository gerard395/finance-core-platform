<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum StoreBankImportArtifactStatus: string
{
    case Success = 'success';
    case IntegrityFailure = 'integrity_failure';
    case StorageFailure = 'storage_failure';
}

<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface SalesDocumentMasterDataStore
{
    public function readMasterData(AdministrationId $administrationId): ?SalesDocumentMasterData;

    public function update(AdministrationId $administrationId, SalesDocumentMasterData $data): bool;
}

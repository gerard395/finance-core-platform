<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class UpdateSalesDocumentMasterData
{
    public function __construct(private SalesDocumentMasterDataStore $store, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, SalesDocumentMasterData $data): bool
    {
        return $this->transactions->run(fn (): bool => $this->store->update($administrationId, $data));
    }
}

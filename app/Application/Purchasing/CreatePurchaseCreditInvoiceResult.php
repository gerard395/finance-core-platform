<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

final readonly class CreatePurchaseCreditInvoiceResult
{
    public function __construct(public PurchaseCreditMutationResult $status, public ?PurchaseCreditInvoiceId $id = null) {}
}

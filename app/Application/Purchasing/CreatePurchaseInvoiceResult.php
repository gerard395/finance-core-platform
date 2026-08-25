<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class CreatePurchaseInvoiceResult
{
    public function __construct(public CreatePurchaseInvoiceStatus $status, public ?PurchaseInvoiceId $id = null) {}
}

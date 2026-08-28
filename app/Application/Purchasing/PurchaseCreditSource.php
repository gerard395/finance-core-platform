<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;

final readonly class PurchaseCreditSource
{
    /** @param array<string, TaxPostingId|null> $taxPostingIds */
    public function __construct(public PurchaseInvoice $invoice, public OpenItemId $payableOpenItemId, public array $taxPostingIds) {}
}

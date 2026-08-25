<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoicePostingIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelPurchaseInvoicePostingIdentityGenerator implements PurchaseInvoicePostingIdentityGenerator
{
    public function journalEntryId(): JournalEntryId
    {
        return new JournalEntryId(new Uuid((string) Str::uuid()));
    }

    public function journalEntryLineId(): JournalEntryLineId
    {
        return new JournalEntryLineId(new Uuid((string) Str::uuid()));
    }

    public function taxPostingId(): TaxPostingId
    {
        return new TaxPostingId(new Uuid((string) Str::uuid()));
    }

    public function openItemId(): OpenItemId
    {
        return new OpenItemId(new Uuid((string) Str::uuid()));
    }
}

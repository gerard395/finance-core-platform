<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditSourceLineClaimId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelPurchaseCreditIdentityGenerator implements PurchaseCreditIdentityGenerator
{
    public function creditId(): PurchaseCreditInvoiceId
    {
        return new PurchaseCreditInvoiceId(new Uuid((string) Str::uuid()));
    }

    public function lineId(): PurchaseCreditInvoiceLineId
    {
        return new PurchaseCreditInvoiceLineId(new Uuid((string) Str::uuid()));
    }

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

    public function postingId(): PurchaseCreditPostingId
    {
        return new PurchaseCreditPostingId(new Uuid((string) Str::uuid()));
    }

    public function claimId(): PurchaseCreditSourceLineClaimId
    {
        return new PurchaseCreditSourceLineClaimId(new Uuid((string) Str::uuid()));
    }
}

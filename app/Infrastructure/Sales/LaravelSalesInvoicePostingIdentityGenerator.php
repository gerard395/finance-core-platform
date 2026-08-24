<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesInvoicePostingIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelSalesInvoicePostingIdentityGenerator implements SalesInvoicePostingIdentityGenerator
{
    public function journalEntryId(): JournalEntryId
    {
        return new JournalEntryId($this->uuid());
    }

    public function journalEntryLineId(): JournalEntryLineId
    {
        return new JournalEntryLineId($this->uuid());
    }

    public function taxPostingId(): TaxPostingId
    {
        return new TaxPostingId($this->uuid());
    }

    public function openItemId(): OpenItemId
    {
        return new OpenItemId($this->uuid());
    }

    private function uuid(): Uuid
    {
        return new Uuid(Str::uuid()->toString());
    }
}

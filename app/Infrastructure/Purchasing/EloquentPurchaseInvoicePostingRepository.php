<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoicePosting;
use App\Application\Purchasing\PurchaseInvoicePostingRepository;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\PurchaseInvoicePostingRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentPurchaseInvoicePostingRepository implements PurchaseInvoicePostingRepository
{
    public function findForInvoice(AdministrationId $administrationId, PurchaseInvoiceId $invoiceId): ?PurchaseInvoicePosting
    {
        $record = PurchaseInvoicePostingRecord::query()->where('administration_id', $administrationId->toString())->where('purchase_invoice_id', $invoiceId->toString())->first();
        if ($record === null) {
            return null;
        }
        $date = DB::table('journal_entries')->where('administration_id', $administrationId->toString())->where('id', $record->getAttribute('journal_entry_id'))->value('posting_date');

        return new PurchaseInvoicePosting($administrationId, $invoiceId, new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))), new OpenItemId(new Uuid($record->getAttribute('open_item_id'))), new PostingDate(new DateTimeImmutable((string) $date)), new DateTimeImmutable((string) $record->getAttribute('created_at')));
    }

    public function append(PurchaseInvoicePosting $posting): bool
    {
        try {
            PurchaseInvoicePostingRecord::query()->create(['administration_id' => $posting->administrationId->toString(), 'purchase_invoice_id' => $posting->purchaseInvoiceId->toString(), 'journal_entry_id' => $posting->journalEntryId->toString(), 'open_item_id' => $posting->openItemId->toString(), 'created_at' => $posting->createdAt]);

            return true;
        } catch (QueryException $exception) {
            if ($this->findForInvoice($posting->administrationId, $posting->purchaseInvoiceId) !== null) {
                return false;
            }
            throw $exception;
        }
    }
}

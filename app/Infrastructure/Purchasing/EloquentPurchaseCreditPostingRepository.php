<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditPosting;
use App\Application\Purchasing\PurchaseCreditPostingReadModel;
use App\Application\Purchasing\PurchaseCreditPostingRepository;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentPurchaseCreditPostingRepository implements PurchaseCreditPostingRepository
{
    public function find(AdministrationId $a, PurchaseCreditInvoiceId $id): ?PurchaseCreditPosting
    {
        $r = DB::table('purchase_credit_invoice_postings')->where('administration_id', $a->toString())->where('purchase_credit_invoice_id', $id->toString())->first();

        return $r === null ? null : new PurchaseCreditPosting(new PurchaseCreditPostingId(new Uuid($r->id)), $a, $id, new JournalEntryId(new Uuid($r->journal_entry_id)), new OpenItemId(new Uuid($r->open_item_id)), new PostingDate(new DateTimeImmutable($r->posting_date)), new DateTimeImmutable($r->created_at));
    }

    public function appendClaims(array $claims): bool
    {
        try {
            foreach ($claims as $c) {
                DB::table('purchase_credit_source_line_claims')->insert(['id' => $c->id->toString(), 'administration_id' => $c->administrationId->toString(), 'source_purchase_invoice_line_id' => $c->sourceLineId->toString(), 'purchase_credit_invoice_id' => $c->creditId->toString(), 'purchase_credit_invoice_line_id' => $c->creditLineId->toString(), 'created_at' => $c->createdAt]);
            }

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }throw $e;
        }
    }

    public function findReadModel(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditPostingReadModel
    {
        $row = DB::table('purchase_credit_invoice_postings as posting')->join('purchase_credit_invoices as credit', function ($join): void {
            $join->on('credit.administration_id', '=', 'posting.administration_id')->on('credit.id', '=', 'posting.purchase_credit_invoice_id');
        })->join('open_items as item', function ($join): void {
            $join->on('item.administration_id', '=', 'posting.administration_id')->on('item.id', '=', 'posting.open_item_id');
        })->where('posting.administration_id', $admin->toString())->where('posting.purchase_credit_invoice_id', $id->toString())->first(['posting.posting_date', 'posting.journal_entry_id', 'posting.open_item_id', 'credit.source_purchase_invoice_id', 'credit.source_payable_open_item_id', 'item.original_amount', 'item.currency']);
        if ($row === null) {
            return null;
        }
        $taxIds = [];
        foreach (DB::table('tax_postings')->where('administration_id', $admin->toString())->where('source_document_type', 'purchase_credit_invoice')->where('source_document_id', $id->toString())->orderBy('source_line_id')->get(['id', 'source_line_id']) as $tax) {
            $taxIds[$tax->source_line_id] = new TaxPostingId(new Uuid($tax->id));
        }
        $lineCount = DB::table('purchase_credit_invoice_lines')->where('administration_id', $admin->toString())->where('purchase_credit_invoice_id', $id->toString())->count();
        $claimCount = DB::table('purchase_credit_source_line_claims')->where('administration_id', $admin->toString())->where('purchase_credit_invoice_id', $id->toString())->count();

        return new PurchaseCreditPostingReadModel($id, new PostingDate(new DateTimeImmutable($row->posting_date)), new JournalEntryId(new Uuid($row->journal_entry_id)), new OpenItemId(new Uuid($row->open_item_id)), new Money($row->original_amount, new Currency($row->currency)), new PurchaseInvoiceId(new Uuid($row->source_purchase_invoice_id)), new OpenItemId(new Uuid($row->source_payable_open_item_id)), $taxIds, $lineCount > 0 && $lineCount === $claimCount);
    }

    public function append(PurchaseCreditPosting $p): bool
    {
        try {
            DB::table('purchase_credit_invoice_postings')->insert(['id' => $p->id->toString(), 'administration_id' => $p->administrationId->toString(), 'purchase_credit_invoice_id' => $p->creditId->toString(), 'journal_entry_id' => $p->journalEntryId->toString(), 'open_item_id' => $p->openItemId->toString(), 'posting_date' => $p->postingDate->value()->format('Y-m-d'), 'created_at' => $p->createdAt]);

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditSource;
use App\Application\Purchasing\PurchaseCreditSourceReader;
use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPurchaseCreditSourceReader implements PurchaseCreditSourceReader
{
    public function __construct(private PurchaseInvoiceRepository $invoices) {}

    public function read(AdministrationId $admin, PurchaseInvoiceId $invoiceId, bool $lock = false): ?PurchaseCreditSource
    {
        $invoice = $lock ? $this->invoices->findForUpdate($admin, $invoiceId) : $this->invoices->find($admin, $invoiceId);
        if ($invoice === null) {
            return null;
        }$q = DB::table('purchase_invoice_postings as p')->join('open_items as o', fn ($j) => $j->on('o.administration_id', '=', 'p.administration_id')->on('o.id', '=', 'p.open_item_id'))->where('p.administration_id', $admin->toString())->where('p.purchase_invoice_id', $invoiceId->toString())->where('o.open_item_type', 'payable')->where('o.side', 'credit')->where('o.relation_id', $invoice->supplierSnapshot()->relationId->toString())->where('o.currency', $invoice->currency()->code());
        if ($lock) {
            $q->lockForUpdate();
        }$payable = $q->select('o.id')->first();
        if ($payable === null) {
            return null;
        }$tax = [];
        foreach (DB::table('tax_postings')->where('administration_id', $admin->toString())->where('source_document_type', 'purchase_invoice')->where('source_document_id', $invoiceId->toString())->where('type', 'original')->where('direction', 'input')->get(['id', 'source_line_id']) as $row) {
            $tax[$row->source_line_id] = new TaxPostingId(new Uuid($row->id));
        }foreach ($invoice->lines() as $line) {
            if (! $line->taxAmount()->isZero() && ! isset($tax[$line->id()->toString()])) {
                return null;
            }$tax[$line->id()->toString()] ??= null;
        }

        return new PurchaseCreditSource($invoice, new OpenItemId(new Uuid($payable->id)), $tax);
    }
}

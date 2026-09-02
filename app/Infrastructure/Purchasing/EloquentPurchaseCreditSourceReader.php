<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Purchasing\PurchaseCreditSource;
use App\Application\Purchasing\PurchaseCreditSourceReader;
use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPurchaseCreditSourceReader implements PurchaseCreditSourceReader
{
    public function __construct(private PurchaseInvoiceRepository $invoices, private TaxPostingReadRepository $taxPostings) {}

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
        }
        $tax = [];
        $byLine = [];
        foreach ($this->taxPostings->findOriginalsForSource($admin, TaxSourceDocumentType::PurchaseInvoice, new TaxSourceDocumentId($invoiceId->uuid())) as $posting) {
            $key = $posting->sourceLineId()->toString();
            $byLine[$key][] = $posting;
            if ($posting->direction()->value === 'input') {
                $tax[$key] = $posting->id();
            }
        }
        foreach ($invoice->lines() as $line) {
            $key = $line->id()->toString();
            if ($line->treatmentSnapshot() !== null && ($byLine[$key] ?? []) === []) {
                return null;
            }
            if ($line->treatmentSnapshot() === null && ! $line->taxAmount()->isZero() && ! isset($tax[$key])) {
                return null;
            }
            $tax[$key] ??= null;
            $byLine[$key] ??= [];
        }

        return new PurchaseCreditSource($invoice, new OpenItemId(new Uuid($payable->id)), $tax, $byLine);
    }
}

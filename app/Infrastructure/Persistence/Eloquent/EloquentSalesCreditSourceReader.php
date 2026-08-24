<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Sales\SalesCreditSource;
use App\Application\Sales\SalesCreditSourceReader;
use App\Application\Sales\SalesCreditSourceStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoicePostingRecord;

final readonly class EloquentSalesCreditSourceReader implements SalesCreditSourceReader
{
    public function __construct(private EloquentSalesInvoiceRepository $invoices, private TaxPostingReadRepository $taxPostings) {}

    public function read(AdministrationId $administrationId, SalesInvoiceId $sourceInvoiceId, ?SalesCreditInvoiceId $currentCreditInvoiceId = null): SalesCreditSource
    {
        $invoice = $this->invoices->findLockedForAdministration($administrationId, $sourceInvoiceId);
        if ($invoice === null) {
            return new SalesCreditSource(SalesCreditSourceStatus::NotFound);
        }
        if (! in_array($invoice->status(), [SalesInvoiceStatus::Posted, SalesInvoiceStatus::Paid], true)) {
            return new SalesCreditSource(SalesCreditSourceStatus::SourceNotPosted);
        }
        if (! SalesInvoicePostingRecord::query()->where('administration_id', $administrationId->toString())->where('sales_invoice_id', $sourceInvoiceId->toString())->exists()) {
            return new SalesCreditSource(SalesCreditSourceStatus::FinancialPostingMissing);
        }
        $existing = SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->where('source_sales_invoice_id', $sourceInvoiceId->toString())->first();
        if ($existing !== null && ($currentCreditInvoiceId === null || $existing->getAttribute('id') !== $currentCreditInvoiceId->toString())) {
            return new SalesCreditSource(SalesCreditSourceStatus::AlreadyCredited);
        }
        $postings = $this->taxPostings->findOriginalsForSource($administrationId, TaxSourceDocumentType::SalesInvoice, new TaxSourceDocumentId($sourceInvoiceId->uuid()));
        if ($postings === []) {
            return new SalesCreditSource(SalesCreditSourceStatus::ReversalSourceMissing);
        }
        $lineIds = array_map(static fn ($line) => $line->id()->toString(), $invoice->lines());
        $postingLineIds = [];
        foreach ($postings as $posting) {
            if ($posting->direction() !== TaxPostingDirection::Output || ! $posting->administrationId()->equals($administrationId)) {
                return new SalesCreditSource(SalesCreditSourceStatus::ReversalSourceInvalid);
            }
            $postingLineIds[] = $posting->sourceLineId()->toString();
        }
        sort($lineIds);
        sort($postingLineIds);
        if ($lineIds !== $postingLineIds) {
            return new SalesCreditSource(SalesCreditSourceStatus::ReversalSourceInvalid);
        }

        return new SalesCreditSource(SalesCreditSourceStatus::Success, $invoice, $postings);
    }
}

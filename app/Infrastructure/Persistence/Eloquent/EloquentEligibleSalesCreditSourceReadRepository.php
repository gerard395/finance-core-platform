<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\EligibleSalesCreditSource;
use App\Application\Sales\EligibleSalesCreditSourceQuery;
use App\Application\Sales\EligibleSalesCreditSourceReadRepository;
use App\Application\Sales\EligibleSalesCreditSourceSortField;
use App\Application\Sales\PaginatedEligibleSalesCreditSources;
use App\Application\Sales\SalesInvoiceReadinessChecker;
use App\Application\Sales\SalesInvoiceReadinessStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class EloquentEligibleSalesCreditSourceReadRepository implements EligibleSalesCreditSourceReadRepository
{
    public function __construct(private EloquentSalesInvoiceRepository $invoices, private SalesInvoiceReadinessChecker $readiness) {}

    public function listEligible(EligibleSalesCreditSourceQuery $query): PaginatedEligibleSalesCreditSources
    {
        $builder = SalesInvoiceRecord::query()
            ->where('sales_invoices.administration_id', $query->administrationId()->toString())
            ->whereIn('sales_invoices.status', [SalesInvoiceStatus::Posted->value, SalesInvoiceStatus::Paid->value]);
        $this->applyEligibility($builder);
        if ($query->search() !== null) {
            $pattern = '%'.addcslashes($query->search(), '\\%_').'%';
            $builder->where(static fn (Builder $search) => $search->whereLike('sales_invoices.sales_invoice_number', $pattern, caseSensitive: false)->orWhereLike('sales_invoices.customer_name_snapshot', $pattern, caseSensitive: false));
        }
        $total = (clone $builder)->count();
        $column = match ($query->sortField()) {
            EligibleSalesCreditSourceSortField::InvoiceDate => 'sales_invoices.invoice_date',
            EligibleSalesCreditSourceSortField::Number => 'sales_invoices.sales_invoice_number',
            EligibleSalesCreditSourceSortField::CustomerName => 'sales_invoices.customer_name_snapshot',
        };
        $records = $builder->orderBy($column, $query->sortDirection()->value)->orderBy('sales_invoices.id')->forPage($query->page(), $query->perPage())->get();
        $items = [];
        foreach ($records as $record) {
            $invoice = $this->invoices->findForAdministration($query->administrationId(), new SalesInvoiceId(new Uuid($record->getAttribute('id'))));
            if ($invoice === null) {
                throw new DomainException('Eligible SalesInvoice disappeared during selector read.');
            }
            $totals = $this->readiness->check($invoice);
            if ($totals->status() !== SalesInvoiceReadinessStatus::Ready || $totals->netTotal() === null || $totals->taxTotal() === null || $totals->grossTotal() === null) {
                throw new DomainException('Eligible SalesInvoice must have exact historical totals.');
            }
            $items[] = new EligibleSalesCreditSource(
                $invoice->id(), new SalesInvoiceNumber($record->getAttribute('sales_invoice_number')),
                new CustomerNumber($record->getAttribute('customer_number_snapshot')), new DisplayName($record->getAttribute('customer_name_snapshot')),
                $invoice->invoiceDate(), $invoice->status(), $invoice->currency(), $totals->netTotal(), $totals->taxTotal(), $totals->grossTotal(),
            );
        }

        return new PaginatedEligibleSalesCreditSources($items, $query->page(), $query->perPage(), $total);
    }

    private function applyEligibility(Builder $builder): void
    {
        $builder
            ->whereExists(static fn (QueryBuilder $posting) => $posting->selectRaw('1')->from('sales_invoice_postings as sip')->whereColumn('sip.administration_id', 'sales_invoices.administration_id')->whereColumn('sip.sales_invoice_id', 'sales_invoices.id'))
            ->whereNotExists(static fn (QueryBuilder $credit) => $credit->selectRaw('1')->from('sales_credit_invoices as sci')->whereColumn('sci.administration_id', 'sales_invoices.administration_id')->whereColumn('sci.source_sales_invoice_id', 'sales_invoices.id'))
            ->whereNotExists(static function (QueryBuilder $line): void {
                $line->selectRaw('1')->from('sales_invoice_lines as sil')
                    ->whereColumn('sil.administration_id', 'sales_invoices.administration_id')
                    ->whereColumn('sil.sales_invoice_id', 'sales_invoices.id')
                    ->whereRaw('(SELECT COUNT(*) FROM tax_postings tp WHERE tp.administration_id = sales_invoices.administration_id AND tp.source_document_type = ? AND tp.source_document_id = sales_invoices.id AND tp.source_line_id = sil.id AND tp.type = ? AND tp.direction = ?) <> 1', [TaxSourceDocumentType::SalesInvoice->value, TaxPostingType::Original->value, TaxPostingDirection::Output->value]);
            })
            ->whereNotExists(static function (QueryBuilder $orphan): void {
                $orphan->selectRaw('1')->from('tax_postings as otp')
                    ->whereColumn('otp.administration_id', 'sales_invoices.administration_id')
                    ->whereColumn('otp.source_document_id', 'sales_invoices.id')
                    ->where('otp.source_document_type', TaxSourceDocumentType::SalesInvoice->value)
                    ->where('otp.type', TaxPostingType::Original->value)
                    ->where(static fn (QueryBuilder $invalid) => $invalid
                        ->where('otp.direction', '<>', TaxPostingDirection::Output->value)
                        ->orWhereNotExists(static fn (QueryBuilder $line) => $line->selectRaw('1')->from('sales_invoice_lines as osil')->whereColumn('osil.administration_id', 'otp.administration_id')->whereColumn('osil.sales_invoice_id', 'otp.source_document_id')->whereColumn('osil.id', 'otp.source_line_id')));
            })
            ->whereNotExists(static function (QueryBuilder $reversal): void {
                $reversal->selectRaw('1')->from('tax_postings as rtp')
                    ->join('tax_postings as original', static fn ($join) => $join->on('original.administration_id', '=', 'rtp.administration_id')->on('original.id', '=', 'rtp.reversed_tax_posting_id'))
                    ->whereColumn('original.administration_id', 'sales_invoices.administration_id')
                    ->whereColumn('original.source_document_id', 'sales_invoices.id')
                    ->where('original.source_document_type', TaxSourceDocumentType::SalesInvoice->value)
                    ->where('original.type', TaxPostingType::Original->value)
                    ->where('rtp.type', TaxPostingType::Reversal->value);
            })
            ->whereExists(static fn (QueryBuilder $line) => $line->selectRaw('1')->from('sales_invoice_lines as required_line')->whereColumn('required_line.administration_id', 'sales_invoices.administration_id')->whereColumn('required_line.sales_invoice_id', 'sales_invoices.id'));
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\PaginatedSalesInvoiceList;
use App\Application\Sales\SalesInvoiceDetail;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceListItem;
use App\Application\Sales\SalesInvoiceListQuery;
use App\Application\Sales\SalesInvoiceListReadRepository;
use App\Application\Sales\SalesInvoiceReadiness;
use App\Application\Sales\SalesInvoiceReadinessChecker;
use App\Application\Sales\SalesInvoiceReadinessStatus;
use App\Application\Sales\SalesInvoiceSortField;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentSalesInvoiceReadRepository implements SalesInvoiceDetailReadRepository, SalesInvoiceListReadRepository
{
    public function __construct(private EloquentSalesInvoiceRepository $invoices, private SalesInvoiceReadinessChecker $readiness) {}

    public function find(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoiceDetail
    {
        $invoice = $this->invoices->findForAdministration($administrationId, $invoiceId);

        return $invoice === null ? null : SalesInvoiceDetail::fromInvoice($invoice, $this->totals($invoice));
    }

    public function search(SalesInvoiceListQuery $query): PaginatedSalesInvoiceList
    {
        $builder = SalesInvoiceRecord::query()->where('administration_id', $query->administrationId()->toString());
        $this->applyFilters($builder, $query);
        $total = (clone $builder)->count();
        $column = match ($query->sortField()) {
            SalesInvoiceSortField::Number => 'sales_invoice_number',
            SalesInvoiceSortField::CustomerName => 'customer_name_snapshot',
            SalesInvoiceSortField::InvoiceDate => 'invoice_date',
            SalesInvoiceSortField::DueDate => 'due_date',
            SalesInvoiceSortField::Status => 'status',
        };
        $records = $builder->orderBy($column, $query->sortDirection()->value)->orderBy('id')->forPage($query->page(), $query->perPage())->get();
        $items = [];
        foreach ($records as $record) {
            $invoice = $this->invoices->findForAdministration($query->administrationId(), new SalesInvoiceId(new Uuid($record->getAttribute('id'))));
            if ($invoice === null) {
                continue;
            }
            $totals = $this->totals($invoice);
            $items[] = new SalesInvoiceListItem($invoice->id(), $invoice->number(), new DisplayName($record->getAttribute('customer_name_snapshot')), $invoice->invoiceDate(), $invoice->dueDate(), $invoice->status(), $invoice->currency(), $totals->netTotal(), $totals->taxTotal(), $totals->grossTotal(), $invoice->sourceOrderId());
        }

        return new PaginatedSalesInvoiceList($items, $query->page(), $query->perPage(), $total);
    }

    private function totals(SalesInvoice $invoice): SalesInvoiceReadiness
    {
        $totals = $this->readiness->check($invoice);
        if ($totals->status() === SalesInvoiceReadinessStatus::MissingLines) {
            $zero = Money::zero($invoice->currency());

            return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::Ready, $zero, $zero, $zero);
        }
        if ($totals->status() !== SalesInvoiceReadinessStatus::Ready) {
            throw new DomainException('Persistent SalesInvoice cannot produce exact totals.');
        }

        return $totals;
    }

    private function applyFilters(Builder $builder, SalesInvoiceListQuery $query): void
    {
        if ($query->status() !== null) {
            $builder->where('status', $query->status()->value);
        }
        if ($query->customerId() !== null) {
            $builder->where('customer_id', $query->customerId()->toString());
        }
        if ($query->dateFrom() !== null) {
            $builder->whereDate('invoice_date', '>=', $query->dateFrom()->format('Y-m-d'));
        }
        if ($query->dateTo() !== null) {
            $builder->whereDate('invoice_date', '<=', $query->dateTo()->format('Y-m-d'));
        }
        if ($query->search() !== null) {
            $pattern = '%'.addcslashes($query->search(), '\\%_').'%';
            $builder->where(static fn (Builder $search) => $search->whereLike('sales_invoice_number', $pattern, caseSensitive: false)->orWhereLike('customer_name_snapshot', $pattern, caseSensitive: false));
        }
    }
}

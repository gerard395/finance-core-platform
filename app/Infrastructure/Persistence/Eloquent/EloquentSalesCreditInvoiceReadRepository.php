<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\PaginatedSalesCreditInvoiceList;
use App\Application\Sales\SalesCreditInvoiceDetail;
use App\Application\Sales\SalesCreditInvoiceDetailReadRepository;
use App\Application\Sales\SalesCreditInvoiceListItem;
use App\Application\Sales\SalesCreditInvoiceListQuery;
use App\Application\Sales\SalesCreditInvoiceListReadRepository;
use App\Application\Sales\SalesCreditInvoiceSortField;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentSalesCreditInvoiceReadRepository implements SalesCreditInvoiceDetailReadRepository, SalesCreditInvoiceListReadRepository
{
    public function __construct(private EloquentSalesCreditInvoiceRepository $credits) {}

    public function find(AdministrationId $administrationId, SalesCreditInvoiceId $id): ?SalesCreditInvoiceDetail
    {
        $credit = $this->credits->findForAdministration($administrationId, $id);
        if ($credit === null) {
            return null;
        }
        $number = SalesInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($credit->sourceInvoiceId()->toString())->value('sales_invoice_number');

        return $number === null ? null : new SalesCreditInvoiceDetail($credit, new SalesInvoiceNumber($number));
    }

    public function search(SalesCreditInvoiceListQuery $query): PaginatedSalesCreditInvoiceList
    {
        $builder = SalesCreditInvoiceRecord::query()->where('administration_id', $query->administrationId->toString());
        if ($query->status !== null) {
            $builder->where('status', $query->status->value);
        }
        if ($query->customerId !== null) {
            $builder->where('customer_id', $query->customerId->toString());
        }
        if ($query->dateFrom !== null) {
            $builder->whereDate('credit_invoice_date', '>=', $query->dateFrom->format('Y-m-d'));
        }
        if ($query->dateTo !== null) {
            $builder->whereDate('credit_invoice_date', '<=', $query->dateTo->format('Y-m-d'));
        }
        if ($query->search !== null) {
            $pattern = '%'.addcslashes($query->search, '\\%_').'%';
            $builder->where(static fn (Builder $search) => $search->whereLike('sales_credit_invoice_number', $pattern, caseSensitive: false)->orWhereLike('customer_name_snapshot', $pattern, caseSensitive: false));
        }
        $total = (clone $builder)->count();
        $column = match ($query->sortField) {
            SalesCreditInvoiceSortField::Number => 'sales_credit_invoice_number', SalesCreditInvoiceSortField::CustomerName => 'customer_name_snapshot', SalesCreditInvoiceSortField::CreditDate => 'credit_invoice_date', SalesCreditInvoiceSortField::Status => 'status'
        };
        $items = [];
        foreach ($builder->orderBy($column, $query->sortDirection->value)->orderBy('id')->forPage($query->page, $query->perPage)->get() as $record) {
            $credit = $this->credits->findForAdministration($query->administrationId, new SalesCreditInvoiceId(new Uuid($record->getAttribute('id'))));
            $sourceNumber = SalesInvoiceRecord::query()->where('administration_id', $query->administrationId->toString())->whereKey($record->getAttribute('source_sales_invoice_id'))->value('sales_invoice_number');
            if ($credit !== null && $sourceNumber !== null) {
                $items[] = new SalesCreditInvoiceListItem($credit->id(), $credit->number(), $credit->sourceInvoiceId(), new SalesInvoiceNumber($sourceNumber), new DisplayName($record->getAttribute('customer_name_snapshot')), new DateTimeImmutable($record->getAttribute('credit_invoice_date')), $credit->status(), $credit->currency(), $credit->total());
            }
        }

        return new PaginatedSalesCreditInvoiceList($items, $query->page, $query->perPage, $total);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\PaginatedQuotationList;
use App\Application\Sales\QuotationDetail;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListItem;
use App\Application\Sales\QuotationListQuery;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationSortField;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\QuotationRecord;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentQuotationReadRepository implements QuotationDetailReadRepository, QuotationListReadRepository
{
    public function __construct(private EloquentQuotationRepository $quotations) {}

    public function find(AdministrationId $administrationId, QuotationId $quotationId): ?QuotationDetail
    {
        $quotation = $this->quotations->findForAdministration($administrationId, $quotationId);

        return $quotation === null ? null : QuotationDetail::fromQuotation($quotation);
    }

    public function search(QuotationListQuery $query): PaginatedQuotationList
    {
        $builder = QuotationRecord::query()->where('administration_id', $query->administrationId()->toString());
        $this->applyFilters($builder, $query);
        $total = (clone $builder)->count();
        $column = match ($query->sortField()) {
            QuotationSortField::Number => 'quotation_number',
            QuotationSortField::CustomerName => 'customer_name_snapshot',
            QuotationSortField::QuotationDate => 'quotation_date',
            QuotationSortField::ExpiryDate => 'expiry_date',
            QuotationSortField::Status => 'status',
        };
        $records = $builder->orderBy($column, $query->sortDirection()->value)->orderBy('id')->forPage($query->page(), $query->perPage())->get();
        $items = [];
        foreach ($records as $record) {
            $quotation = $this->quotations->findForAdministration($query->administrationId(), new QuotationId(new Uuid($record->getAttribute('id'))));
            if ($quotation === null || $quotation->customerSnapshot() === null) {
                continue;
            }
            $items[] = new QuotationListItem($quotation->id(), $quotation->number(), new DisplayName($record->getAttribute('customer_name_snapshot')), $quotation->quotationDate(), $quotation->expiryDate(), $quotation->status(), $quotation->currency(), $quotation->total());
        }

        return new PaginatedQuotationList($items, $query->page(), $query->perPage(), $total);
    }

    private function applyFilters(Builder $builder, QuotationListQuery $query): void
    {
        if ($query->status() !== null) {
            $builder->where('status', $query->status()->value);
        }
        if ($query->customerId() !== null) {
            $builder->where('customer_id', $query->customerId()->toString());
        }
        if ($query->dateFrom() !== null) {
            $builder->whereDate('quotation_date', '>=', $query->dateFrom()->format('Y-m-d'));
        }
        if ($query->dateTo() !== null) {
            $builder->whereDate('quotation_date', '<=', $query->dateTo()->format('Y-m-d'));
        }
        if ($query->search() !== null) {
            $pattern = '%'.addcslashes($query->search(), '\\%_').'%';
            $builder->where(static fn (Builder $search) => $search->whereLike('quotation_number', $pattern, caseSensitive: false)->orWhereLike('customer_name_snapshot', $pattern, caseSensitive: false));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\PaginatedRelationList;
use App\Application\Relations\RelationClassificationFilter;
use App\Application\Relations\RelationListItem;
use App\Application\Relations\RelationListQuery;
use App\Application\Relations\RelationListReadRepository;
use App\Application\Relations\RelationSortField;
use App\Application\Relations\RelationStatusFilter;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentRelationListReadRepository implements RelationListReadRepository
{
    public function search(RelationListQuery $query): PaginatedRelationList
    {
        $builder = RelationRecord::query()->select('relations.*')
            ->selectSub($this->classificationExistsQuery('customers', $query), 'is_customer')
            ->selectSub($this->classificationExistsQuery('suppliers', $query), 'is_supplier')
            ->where('relations.administration_id', $query->administrationId()->toString());
        $this->applySearch($builder, $query);
        $this->applyClassification($builder, $query);
        $this->applyStatus($builder, $query);
        $total = (clone $builder)->count('relations.id');
        $column = match ($query->sortField()) {
            RelationSortField::DisplayName => 'relations.display_name', RelationSortField::Code => 'relations.code', RelationSortField::Status => 'relations.active'
        };
        $records = $builder->orderBy($column, $query->sortDirection()->value)->orderBy('relations.id')->forPage($query->page(), $query->perPage())->get();
        $items = $records->map(static fn (RelationRecord $record): RelationListItem => new RelationListItem(
            new RelationId(new Uuid($record->getAttribute('id'))), new RelationCode($record->getAttribute('code')),
            new DisplayName($record->getAttribute('display_name')), (bool) $record->getAttribute('active'),
            (bool) $record->getAttribute('is_customer'), (bool) $record->getAttribute('is_supplier'),
        ))->all();

        return new PaginatedRelationList($items, $query->page(), $query->perPage(), $total);
    }

    private function applySearch(Builder $builder, RelationListQuery $query): void
    {
        if ($query->searchTerm() === null) {
            return;
        }
        $pattern = '%'.addcslashes($query->searchTerm(), '\\%_').'%';
        $builder->where(static function (Builder $search) use ($pattern): void {
            $search->whereLike('relations.code', $pattern, caseSensitive: false)->orWhereLike('relations.display_name', $pattern, caseSensitive: false);
        });
    }

    private function applyClassification(Builder $builder, RelationListQuery $query): void
    {
        match ($query->classification()) {
            RelationClassificationFilter::All => null,
            RelationClassificationFilter::Customer => $this->whereClassificationExists($builder, 'customers', $query),
            RelationClassificationFilter::Supplier => $this->whereClassificationExists($builder, 'suppliers', $query),
            RelationClassificationFilter::Both => $this->whereBothClassificationsExist($builder, $query),
            RelationClassificationFilter::Neither => $this->whereNeitherClassificationExists($builder, $query),
        };
    }

    private function applyStatus(Builder $builder, RelationListQuery $query): void
    {
        match ($query->status()) {
            RelationStatusFilter::All => null, RelationStatusFilter::Active => $builder->where('relations.active', true), RelationStatusFilter::Inactive => $builder->where('relations.active', false)
        };
    }

    private function whereBothClassificationsExist(Builder $builder, RelationListQuery $query): void
    {
        $this->whereClassificationExists($builder, 'customers', $query);
        $this->whereClassificationExists($builder, 'suppliers', $query);
    }

    private function whereNeitherClassificationExists(Builder $builder, RelationListQuery $query): void
    {
        $this->whereClassificationExists($builder, 'customers', $query, false);
        $this->whereClassificationExists($builder, 'suppliers', $query, false);
    }

    private function whereClassificationExists(Builder $builder, string $table, RelationListQuery $query, bool $exists = true): void
    {
        $method = $exists ? 'whereExists' : 'whereNotExists';
        $builder->{$method}($this->classificationExistsQuery($table, $query));
    }

    private function classificationExistsQuery(string $table, RelationListQuery $query): Builder
    {
        return RelationRecord::query()->selectRaw('1')->from($table)
            ->where($table.'.administration_id', $query->administrationId()->toString())
            ->where($table.'.active', true)
            ->whereColumn($table.'.administration_id', 'relations.administration_id')->whereColumn($table.'.relation_id', 'relations.id');
    }
}

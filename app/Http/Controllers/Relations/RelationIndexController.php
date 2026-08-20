<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\RelationClassificationFilter;
use App\Application\Relations\RelationListQuery;
use App\Application\Relations\RelationListReadRepository;
use App\Application\Relations\RelationSortDirection;
use App\Application\Relations\RelationSortField;
use App\Application\Relations\RelationStatusFilter;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RelationIndexController extends Controller
{
    public function __construct(
        private readonly RelationListReadRepository $relations,
        private readonly PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'classification' => ['nullable', Rule::enum(RelationClassificationFilter::class)],
            'status' => ['nullable', Rule::enum(RelationStatusFilter::class)],
            'sort' => ['nullable', Rule::enum(RelationSortField::class)],
            'direction' => ['nullable', Rule::enum(RelationSortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in(RelationListQuery::ALLOWED_PER_PAGE)],
        ]);
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $query = new RelationListQuery(
            $context->administration->id(),
            $validated['q'] ?? null,
            RelationClassificationFilter::from($validated['classification'] ?? RelationClassificationFilter::All->value),
            RelationStatusFilter::from($validated['status'] ?? RelationStatusFilter::All->value),
            RelationSortField::from($validated['sort'] ?? RelationSortField::DisplayName->value),
            RelationSortDirection::from($validated['direction'] ?? RelationSortDirection::Ascending->value),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? RelationListQuery::DEFAULT_PER_PAGE),
        );
        $result = $this->relations->search($query);
        $queryParameters = [
            'q' => $query->searchTerm(),
            'classification' => $query->classification()->value,
            'status' => $query->status()->value,
            'sort' => $query->sortField()->value,
            'direction' => $query->sortDirection()->value,
            'per_page' => $query->perPage(),
        ];

        return view('relations.index', [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'relations' => $result,
            'query' => $query,
            'queryParameters' => array_filter($queryParameters, static fn (mixed $value): bool => $value !== null),
            'hasActiveFilters' => $query->searchTerm() !== null
                || $query->classification() !== RelationClassificationFilter::All
                || $query->status() !== RelationStatusFilter::All,
            'canViewRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id()),
        ]);
    }
}

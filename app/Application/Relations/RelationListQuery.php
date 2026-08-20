<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use InvalidArgumentException;

final readonly class RelationListQuery
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** @var list<int> */
    public const array ALLOWED_PER_PAGE = [25, 50, 100];

    private ?string $searchTerm;

    public function __construct(
        private AdministrationId $administrationId,
        ?string $searchTerm = null,
        private RelationClassificationFilter $classification = RelationClassificationFilter::All,
        private RelationStatusFilter $status = RelationStatusFilter::All,
        private RelationSortField $sortField = RelationSortField::DisplayName,
        private RelationSortDirection $sortDirection = RelationSortDirection::Ascending,
        private int $page = 1,
        private int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Relation list page must be at least 1.');
        }
        if (! in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            throw new InvalidArgumentException('Relation list page size must be one of 25, 50 or 100.');
        }
        $normalized = $searchTerm === null ? null : trim($searchTerm);
        $this->searchTerm = $normalized === '' ? null : $normalized;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function searchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function classification(): RelationClassificationFilter
    {
        return $this->classification;
    }

    public function status(): RelationStatusFilter
    {
        return $this->status;
    }

    public function sortField(): RelationSortField
    {
        return $this->sortField;
    }

    public function sortDirection(): RelationSortDirection
    {
        return $this->sortDirection;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }
}

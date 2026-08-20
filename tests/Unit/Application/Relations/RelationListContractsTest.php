<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Relations;

use App\Application\Relations\PaginatedRelationList;
use App\Application\Relations\RelationClassificationFilter;
use App\Application\Relations\RelationListQuery;
use App\Application\Relations\RelationSortDirection;
use App\Application\Relations\RelationSortField;
use App\Application\Relations\RelationStatusFilter;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ValueError;

final class RelationListContractsTest extends TestCase
{
    public function test_query_has_safe_defaults_and_normalizes_search(): void
    {
        $query = new RelationListQuery($this->administrationId(), '  Alpha  ');

        self::assertSame('Alpha', $query->searchTerm());
        self::assertSame(RelationClassificationFilter::All, $query->classification());
        self::assertSame(RelationStatusFilter::All, $query->status());
        self::assertSame(RelationSortField::DisplayName, $query->sortField());
        self::assertSame(RelationSortDirection::Ascending, $query->sortDirection());
        self::assertSame(1, $query->page());
        self::assertSame(25, $query->perPage());
        self::assertNull((new RelationListQuery($this->administrationId(), '   '))->searchTerm());
    }

    public function test_page_and_page_size_are_bounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelationListQuery($this->administrationId(), page: 0);
    }

    public function test_excessive_or_non_allowlisted_page_size_is_rejected(): void
    {
        foreach ([1, 24, 101, 1000] as $perPage) {
            try {
                new RelationListQuery($this->administrationId(), perPage: $perPage);
                self::fail("Page size {$perPage} should have been rejected.");
            } catch (InvalidArgumentException) {
                self::assertNotContains($perPage, RelationListQuery::ALLOWED_PER_PAGE);
            }
        }
    }

    public function test_raw_sort_or_filter_values_cannot_enter_typed_query(): void
    {
        $this->expectException(ValueError::class);
        RelationSortField::from('display_name desc; drop table relations');
    }

    public function test_paginated_result_reports_empty_and_next_page_state(): void
    {
        $empty = new PaginatedRelationList([], 1, 25, 0);
        $page = new PaginatedRelationList([], 1, 25, 26);

        self::assertSame(1, $empty->lastPage());
        self::assertFalse($empty->hasNextPage());
        self::assertSame(2, $page->lastPage());
        self::assertTrue($page->hasNextPage());
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId(new Uuid('10000000-0000-4000-8000-000000000001'));
    }
}

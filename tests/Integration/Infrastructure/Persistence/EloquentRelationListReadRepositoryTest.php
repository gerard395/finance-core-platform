<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Relations\PaginatedRelationList;
use App\Application\Relations\RelationClassificationFilter;
use App\Application\Relations\RelationListQuery;
use App\Application\Relations\RelationListReadRepository;
use App\Application\Relations\RelationSortDirection;
use App\Application\Relations\RelationSortField;
use App\Application\Relations\RelationStatusFilter;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentCustomerRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationListReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSupplierRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentRelationListReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_EMPTY = '30000000-0000-4000-8000-000000000001';

    private EloquentRelationListReadRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentRelationListReadRepository;
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMINISTRATION_A, 'ADMIN_A'));
        $administrations->save($this->administration(self::ADMINISTRATION_B, 'ADMIN_B'));
        $administrations->save($this->administration(self::ADMINISTRATION_EMPTY, 'EMPTY'));
        $this->seedCoreRelations();
    }

    public function test_empty_administration_returns_empty_page(): void
    {
        $result = $this->repository->search(new RelationListQuery($this->administrationId(self::ADMINISTRATION_EMPTY)));

        self::assertSame([], $result->items());
        self::assertSame(0, $result->total());
        self::assertSame(1, $result->lastPage());
        self::assertFalse($result->hasNextPage());
    }

    public function test_search_matches_code_and_display_name_case_insensitively_and_treats_wildcards_literally(): void
    {
        self::assertSame(['A-01'], $this->codes($this->searchRelations(search: 'a-01')->items()));
        self::assertSame(['A-02'], $this->codes($this->searchRelations(search: 'supplier')->items()));
        self::assertSame([], $this->codes($this->searchRelations(search: 'missing')->items()));
        self::assertSame(['A-05'], $this->codes($this->searchRelations(search: '100%')->items()));
        self::assertSame([], $this->codes($this->searchRelations(search: "%'; OR 1=1 --")->items()));
        self::assertSame(5, $this->searchRelations(search: '   ')->total());
    }

    public function test_all_classification_filters_use_active_classifications_without_duplicates(): void
    {
        self::assertSame(5, $this->searchRelations(classification: RelationClassificationFilter::All)->total());
        self::assertSame(['A-01', 'A-03'], $this->sortedCodes($this->searchRelations(classification: RelationClassificationFilter::Customer)->items()));
        self::assertSame(['A-02', 'A-03'], $this->sortedCodes($this->searchRelations(classification: RelationClassificationFilter::Supplier)->items()));
        self::assertSame(['A-03'], $this->codes($this->searchRelations(classification: RelationClassificationFilter::Both)->items()));
        self::assertSame(['A-04', 'A-05'], $this->sortedCodes($this->searchRelations(classification: RelationClassificationFilter::Neither)->items()));
    }

    public function test_relation_status_filters_are_independent_from_classification_status(): void
    {
        self::assertSame(['A-01', 'A-03', 'A-04', 'A-05'], $this->sortedCodes($this->searchRelations(status: RelationStatusFilter::Active)->items()));
        self::assertSame(['A-02'], $this->codes($this->searchRelations(status: RelationStatusFilter::Inactive)->items()));
        self::assertSame(5, $this->searchRelations(status: RelationStatusFilter::All)->total());

        $inactiveClassifications = $this->searchRelations(search: 'Percent 100%')->items()[0];
        self::assertFalse($inactiveClassifications->isCustomer());
        self::assertFalse($inactiveClassifications->isSupplier());
    }

    public function test_sorting_is_allowlisted_and_deterministic(): void
    {
        self::assertSame(['Alpha Customer', 'Beta Supplier', 'Delta Neither', 'Gamma Both', 'Percent 100%'], $this->names($this->searchRelations()->items()));
        self::assertSame(['Percent 100%', 'Gamma Both', 'Delta Neither', 'Beta Supplier', 'Alpha Customer'], $this->names($this->searchRelations(direction: RelationSortDirection::Descending)->items()));
        self::assertSame(['A-05', 'A-04', 'A-03', 'A-02', 'A-01'], $this->codes($this->searchRelations(sort: RelationSortField::Code, direction: RelationSortDirection::Descending)->items()));
    }

    public function test_pagination_is_executed_in_database_and_reports_totals(): void
    {
        for ($sequence = 6; $sequence <= 32; $sequence++) {
            $this->saveRelation(self::ADMINISTRATION_A, $sequence, sprintf('A-%02d', $sequence), sprintf('Relation %02d', $sequence));
        }

        $first = $this->searchRelations(page: 1);
        $second = $this->searchRelations(page: 2);

        self::assertCount(25, $first->items());
        self::assertCount(7, $second->items());
        self::assertSame(32, $first->total());
        self::assertSame(2, $first->lastPage());
        self::assertTrue($first->hasNextPage());
        self::assertFalse($second->hasNextPage());
        self::assertSame([], array_intersect($this->codes($first->items()), $this->codes($second->items())));
    }

    public function test_tenant_filter_applies_to_search_classifications_and_read_model(): void
    {
        $result = $this->searchRelations(search: 'Alpha Customer', classification: RelationClassificationFilter::Customer);

        self::assertSame(1, $result->total());
        $item = $result->items()[0];
        self::assertSame('A-01', $item->code()->toString());
        self::assertSame('Alpha Customer', $item->displayName()->toString());
        self::assertTrue($item->isActive());
        self::assertTrue($item->isCustomer());
        self::assertFalse($item->isSupplier());
        self::assertInstanceOf(RelationListReadRepository::class, $this->app->make(RelationListReadRepository::class));
    }

    private function seedCoreRelations(): void
    {
        $first = $this->saveRelation(self::ADMINISTRATION_A, 1, 'A-01', 'Alpha Customer');
        $second = $this->saveRelation(self::ADMINISTRATION_A, 2, 'A-02', 'Beta Supplier', false);
        $third = $this->saveRelation(self::ADMINISTRATION_A, 3, 'A-03', 'Gamma Both');
        $this->saveRelation(self::ADMINISTRATION_A, 4, 'A-04', 'Delta Neither');
        $this->saveRelation(self::ADMINISTRATION_A, 5, 'A-05', 'Percent 100%');
        $tenantB = $this->saveRelation(self::ADMINISTRATION_B, 101, 'A-01', 'Alpha Customer');
        $customers = new EloquentCustomerRepository;
        $suppliers = new EloquentSupplierRepository;
        $customers->save($this->administrationId(self::ADMINISTRATION_A), $this->customer(1, $first->id()));
        $suppliers->save($this->administrationId(self::ADMINISTRATION_A), $this->supplier(2, $second->id()));
        $customers->save($this->administrationId(self::ADMINISTRATION_A), $this->customer(3, $third->id()));
        $suppliers->save($this->administrationId(self::ADMINISTRATION_A), $this->supplier(3, $third->id()));
        $fifth = $this->relationId(5);
        $customers->save($this->administrationId(self::ADMINISTRATION_A), $this->customer(5, $fifth, false));
        $suppliers->save($this->administrationId(self::ADMINISTRATION_A), $this->supplier(5, $fifth, false));
        $customers->save($this->administrationId(self::ADMINISTRATION_B), $this->customer(101, $tenantB->id()));
    }

    private function searchRelations(
        ?string $search = null,
        RelationClassificationFilter $classification = RelationClassificationFilter::All,
        RelationStatusFilter $status = RelationStatusFilter::All,
        RelationSortField $sort = RelationSortField::DisplayName,
        RelationSortDirection $direction = RelationSortDirection::Ascending,
        int $page = 1,
    ): PaginatedRelationList {
        return $this->repository->search(new RelationListQuery($this->administrationId(self::ADMINISTRATION_A), $search, $classification, $status, $sort, $direction, $page));
    }

    private function saveRelation(string $administrationId, int $sequence, string $code, string $name, bool $active = true): Relation
    {
        $relation = new Relation($this->relationId($sequence), new RelationCode($code), new DisplayName($name), $active);
        (new EloquentRelationRepository)->save($this->administrationId($administrationId), $relation);

        return $relation;
    }

    private function customer(int $sequence, RelationId $relationId, bool $active = true): Customer
    {
        return new Customer(new CustomerId($this->uuid('4', $sequence)), $relationId, new CustomerNumber(sprintf('C-%03d', $sequence)), $active);
    }

    private function supplier(int $sequence, RelationId $relationId, bool $active = true): Supplier
    {
        return new Supplier(new SupplierId($this->uuid('5', $sequence)), $relationId, new SupplierNumber(sprintf('S-%03d', $sequence)), $active);
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId($this->uuid('6', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration($this->administrationId($id), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function codes(array $items): array
    {
        return array_map(static fn ($item): string => $item->code()->toString(), $items);
    }

    private function sortedCodes(array $items): array
    {
        $codes = $this->codes($items);
        sort($codes);

        return $codes;
    }

    private function names(array $items): array
    {
        return array_map(static fn ($item): string => $item->displayName()->toString(), $items);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListQuery;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationSortDirection;
use App\Application\Sales\QuotationSortField;
use App\Application\Sales\QuotationWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentQuotationReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentQuotationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\QuotationLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentQuotationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a1000000-0000-4000-8000-000000000001';

    private const B = 'b1000000-0000-4000-8000-000000000001';

    private EloquentQuotationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentQuotationRepository;
        $this->tenant(self::A, 'A');
        $this->tenant(self::B, 'B');
    }

    public function test_all_statuses_snapshot_dates_currency_lines_and_exact_totals_roundtrip(): void
    {
        foreach (QuotationStatus::cases() as $index => $status) {
            $quotation = $this->quotation($index + 1, $status);
            self::assertSame(QuotationWriteResult::Success, $this->repository->create($this->admin(self::A), $quotation));
            $read = $this->repository->findForAdministration($this->admin(self::A), $quotation->id());

            self::assertNotNull($read);
            self::assertSame($status, $read->status());
            self::assertSame('Customer A', $read->customerSnapshot()?->displayName()->value());
            self::assertSame('C-A', $read->customerSnapshot()?->customerNumber()->value());
            self::assertSame('2026-08-21', $read->quotationDate()->format('Y-m-d'));
            self::assertSame('2026-09-21', $read->expiryDate()?->format('Y-m-d'));
            self::assertSame('EUR', $read->currency()->code());
            self::assertSame('1.2345', $read->lines()[0]->quantity()->value());
            self::assertSame('10', $read->lines()[0]->unitPrice()->amount());
            self::assertSame($quotation->total()->amount(), $read->total()->amount());
        }
    }

    public function test_tenant_isolation_duplicate_constraints_and_same_tenant_line_fk_are_database_safe(): void
    {
        $quotation = $this->quotation(1, QuotationStatus::Draft);
        self::assertSame(QuotationWriteResult::Success, $this->repository->create($this->admin(self::A), $quotation));
        self::assertNull($this->repository->findForAdministration($this->admin(self::B), $quotation->id()));
        self::assertSame(QuotationWriteResult::DuplicateIdentity, $this->repository->create($this->admin(self::A), $quotation));
        self::assertSame(QuotationWriteResult::DuplicateNumber, $this->repository->create($this->admin(self::A), $this->quotation(2, QuotationStatus::Draft, 'Q000001')));

        $this->expectException(QueryException::class);
        QuotationLineRecord::query()->create(['id' => 'd1000000-0000-4000-8000-000000000099', 'administration_id' => self::B, 'quotation_id' => $quotation->id()->toString(), 'description' => 'Cross tenant', 'quantity' => '1', 'unit_price_amount' => '1', 'currency' => 'EUR']);
    }

    public function test_update_is_not_upsert_and_syncs_draft_lines_only(): void
    {
        $quotation = $this->quotation(1, QuotationStatus::Draft);
        $this->repository->create($this->admin(self::A), $quotation);
        $quotation->changeDates(new DateTimeImmutable('2026-08-22'), null);
        $quotation->updateLine($this->line(1, '2', '5'));
        $quotation->addLine($this->line(2, '1', '3'));
        self::assertSame(QuotationWriteResult::Success, $this->repository->update($this->admin(self::A), $quotation));

        $read = $this->repository->findForAdministration($this->admin(self::A), $quotation->id());
        self::assertSame('2026-08-22', $read?->quotationDate()->format('Y-m-d'));
        self::assertNull($read?->expiryDate());
        self::assertCount(2, $read?->lines());
        self::assertSame('13', $read?->total()->amount());
        self::assertSame(QuotationWriteResult::NotFound, $this->repository->update($this->admin(self::B), $quotation));
    }

    public function test_list_and_detail_are_tenant_filtered_searchable_sortable_and_paginated_without_duplicates(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->repository->create($this->admin(self::A), $this->quotation($i, $i === 1 ? QuotationStatus::Sent : QuotationStatus::Draft, sprintf('Q%06d', $i)));
        }
        $reads = new EloquentQuotationReadRepository($this->repository);
        $page = $reads->search(new QuotationListQuery($this->admin(self::A), sortField: QuotationSortField::Number, sortDirection: QuotationSortDirection::Ascending));
        self::assertSame(26, $page->total());
        self::assertCount(25, $page->items());
        self::assertSame('Q000001', $page->items()[0]->number()->value());
        self::assertSame($this->quotation(1, QuotationStatus::Sent)->total()->amount(), $page->items()[0]->netTotal()->amount());
        self::assertCount(1, $reads->search(new QuotationListQuery($this->admin(self::A), search: 'Q000001', status: QuotationStatus::Sent))->items());
        self::assertSame(0, $reads->search(new QuotationListQuery($this->admin(self::B)))->total());

        $detail = $reads->find($this->admin(self::A), $this->qid(1));
        self::assertSame('Customer A', $detail?->customer()->displayName()->value());
        self::assertCount(1, $detail?->lines());
        self::assertNull($reads->find($this->admin(self::B), $this->qid(1)));
        self::assertInstanceOf(EloquentQuotationReadRepository::class, $this->app->make(QuotationListReadRepository::class));
        self::assertInstanceOf(EloquentQuotationReadRepository::class, $this->app->make(QuotationDetailReadRepository::class));
    }

    private function quotation(int $id, QuotationStatus $status, ?string $number = null): Quotation
    {
        return Quotation::reconstitute($this->qid($id), new QuotationNumber($number ?? sprintf('Q%06d', $id)), $this->admin(self::A), $this->customer(self::A), new Currency('EUR'), $status, new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-21'), [$this->line($id)], $this->snapshot(self::A));
    }

    private function line(int $id, string $quantity = '1.2345', string $amount = '10'): QuotationLine
    {
        return new QuotationLine(new QuotationLineId(new Uuid(sprintf('d1000000-0000-4000-8000-%012d', $id))), new LineDescription('Consulting'), new Quantity($quantity), new Money($amount, new Currency('EUR')));
    }

    private function qid(int $id): QuotationId
    {
        return new QuotationId(new Uuid(sprintf('c1000000-0000-4000-8000-%012d', $id)));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function customer(string $tenant): CustomerId
    {
        return new CustomerId(new Uuid($tenant === self::A ? 'a3000000-0000-4000-8000-000000000001' : 'b3000000-0000-4000-8000-000000000001'));
    }

    private function relation(string $tenant): RelationId
    {
        return new RelationId(new Uuid($tenant === self::A ? 'a2000000-0000-4000-8000-000000000001' : 'b2000000-0000-4000-8000-000000000001'));
    }

    private function snapshot(string $tenant): SalesCustomerSnapshot
    {
        return new SalesCustomerSnapshot($this->customer($tenant), $this->relation($tenant), new CustomerNumber('C-'.strtoupper($tenant[0])), new DisplayName('Customer '.strtoupper($tenant[0])));
    }

    private function tenant(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => 'QT-'.$suffix, 'name' => 'Quotation tenant '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
        RelationRecord::query()->create(['id' => $this->relation($id)->toString(), 'administration_id' => $id, 'code' => 'REL-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true]);
        CustomerRecord::query()->create(['id' => $this->customer($id)->toString(), 'administration_id' => $id, 'relation_id' => $this->relation($id)->toString(), 'customer_number' => 'C-'.$suffix, 'active' => true]);
    }
}

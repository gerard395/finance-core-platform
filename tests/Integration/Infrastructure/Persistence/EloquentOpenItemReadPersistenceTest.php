<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Reporting\OpenItemsReport;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentOpenItemRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemSettlementRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EloquentOpenItemReadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private EloquentOpenItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentOpenItemRepository;
        $this->createAdministration(self::ADMINISTRATION_A, 'A');
        $this->createAdministration(self::ADMINISTRATION_B, 'B');

        foreach ([self::ADMINISTRATION_A, self::ADMINISTRATION_B] as $administration) {
            for ($sequence = 1; $sequence <= 5; $sequence++) {
                $this->createPostedEntry($administration, $sequence);
            }
        }
    }

    public function test_open_item_basis_roundtrips_exactly_without_derived_state_columns(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '1000.12345678');
        $this->repository->append($item);

        $read = $this->read(self::ADMINISTRATION_A, '2026-01-31');

        self::assertCount(1, $read);
        self::assertTrue($item->id()->equals($read[0]->id()));
        self::assertTrue($item->administrationId()->equals($read[0]->administrationId()));
        self::assertTrue($item->relationId()->equals($read[0]->relationId()));
        self::assertTrue($item->journalEntryId()->equals($read[0]->journalEntryId()));
        self::assertSame(OpenItemType::Receivable, $read[0]->type());
        self::assertSame('receivable', OpenItemRecord::query()->firstOrFail()->getAttribute('open_item_type'));
        self::assertSame('1000.12345678', $read[0]->originalAmount()->amount());
        self::assertSame('EUR', $read[0]->originalAmount()->currency()->code());
        self::assertSame('2026-01-01', $read[0]->openedOn()->value()->format('Y-m-d'));
        self::assertSame(OpenItemStatus::Open, $read[0]->status());
        self::assertFalse(
            in_array('open_amount', OpenItemRecord::query()->firstOrFail()->getConnection()->getSchemaBuilder()->getColumnListing('open_items'), true),
        );
        self::assertFalse(
            in_array('status', OpenItemRecord::query()->firstOrFail()->getConnection()->getSchemaBuilder()->getColumnListing('open_items'), true),
        );
    }

    public function test_payable_type_roundtrips_with_settlement_history_and_as_of_read(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '100', type: OpenItemType::Payable);
        $this->repository->append($item);
        $settlement = $this->apply($item, 1, '2026-01-15', '40', 2);
        $this->repository->appendSettlement($item, $settlement);

        $read = $this->read(self::ADMINISTRATION_A, '2026-01-31')[0];

        self::assertSame(OpenItemType::Payable, $read->type());
        self::assertSame('payable', OpenItemRecord::query()->firstOrFail()->getAttribute('open_item_type'));
        self::assertCount(1, $read->settlements());
        self::assertSame('60', $read->openAmountAt($this->date('2026-01-31'))->amount());
    }

    public function test_database_requires_open_item_type_without_default_and_rejects_invalid_values(): void
    {
        $column = DB::selectOne(<<<'SQL'
            SELECT COLUMN_DEFAULT, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'open_items'
              AND COLUMN_NAME = 'open_item_type'
            SQL);

        self::assertNotNull($column);
        self::assertSame('NO', $column->IS_NULLABLE);
        self::assertNull($column->COLUMN_DEFAULT);

        try {
            OpenItemRecord::query()->create([
                'id' => '32000000-0000-4000-8000-000000000001',
                'administration_id' => self::ADMINISTRATION_A,
                'relation_id' => '42000000-0000-4000-8000-000000000001',
                'journal_entry_id' => $this->journalEntryId(self::ADMINISTRATION_A, 1)->toString(),
                'open_item_type' => 'other',
                'original_amount' => '100',
                'currency' => 'EUR',
                'opened_on' => '2026-01-01',
            ]);
            self::fail('OpenItem type must be restricted to the Domain values.');
        } catch (QueryException) {
            self::assertSame(0, OpenItemRecord::query()->count());
        }
    }

    public function test_full_future_history_is_hydrated_and_domain_reproduces_as_of_amounts(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '1000');
        $this->repository->append($item);
        $first = $this->apply($item, 1, '2026-01-15', '400', 2);
        $this->repository->appendSettlement($item, $first);
        $second = $this->apply($item, 2, '2026-02-10', '600', 3);
        $this->repository->appendSettlement($item, $second);

        $read = $this->read(self::ADMINISTRATION_A, '2026-01-31')[0];

        self::assertCount(2, $read->settlements());
        self::assertSame('1000', $read->openAmountAt($this->date('2026-01-10'))->amount());
        self::assertSame('600', $read->openAmountAt($this->date('2026-01-20'))->amount());
        self::assertSame('600', $read->openAmountAt($this->date('2026-01-31'))->amount());
        self::assertSame('0', $read->openAmountAt($this->date('2026-02-28'))->amount());
        self::assertSame([$first->id()->toString(), $second->id()->toString()], array_map(
            static fn (OpenItemSettlement $settlement): string => $settlement->id()->toString(),
            $read->settlements(),
        ));
    }

    public function test_applied_and_reversal_facts_roundtrip_append_only(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '1000');
        $this->repository->append($item);
        $applied = $this->apply($item, 1, '2026-01-15', '400', 2);
        $this->repository->appendSettlement($item, $applied);
        $item->reverseSettlement($this->settlementId(2), $this->date('2026-02-01'), $applied->id(), $this->journalEntryId(self::ADMINISTRATION_A, 3));
        $reversal = $item->settlement($this->settlementId(2));
        self::assertNotNull($reversal);
        $this->repository->appendSettlement($item, $reversal);

        $read = $this->read(self::ADMINISTRATION_A, '2026-03-01')[0];

        self::assertCount(2, $read->settlements());
        self::assertSame(OpenItemSettlementType::Applied, $read->settlements()[0]->type());
        self::assertSame(OpenItemSettlementType::Reversal, $read->settlements()[1]->type());
        self::assertTrue($read->settlements()[1]->reversedSettlementId()?->equals($applied->id()));
        self::assertSame('600', $read->openAmountAt($this->date('2026-01-31'))->amount());
        self::assertSame('1000', $read->openAmountAt($this->date('2026-02-01'))->amount());
        self::assertSame(2, OpenItemSettlementRecord::query()->count());
    }

    public function test_as_of_read_filters_opening_date_and_administration_only(): void
    {
        $this->repository->append($this->openItem(self::ADMINISTRATION_A, '100', '2026-01-01', 1));
        $this->repository->append($this->openItem(self::ADMINISTRATION_A, '200', '2026-02-01', 2));
        $this->repository->append($this->openItem(self::ADMINISTRATION_B, '300', '2026-01-01', 3));

        $read = $this->read(self::ADMINISTRATION_A, '2026-01-31');

        self::assertCount(1, $read);
        self::assertSame('100', $read[0]->originalAmount()->amount());
    }

    public function test_cross_tenant_source_entries_are_rejected_atomically(): void
    {
        $item = new OpenItem(
            new OpenItemId(new Uuid('30000000-0000-4000-8000-000000000001')),
            $this->administrationId(self::ADMINISTRATION_A),
            new RelationId(new Uuid('40000000-0000-4000-8000-000000000001')),
            $this->journalEntryId(self::ADMINISTRATION_B, 1),
            OpenItemType::Receivable,
            new Money('100', new Currency('EUR')),
            $this->date('2026-01-01'),
        );

        try {
            $this->repository->append($item);
            self::fail('Cross-tenant source entry must be rejected.');
        } catch (DomainException) {
            self::assertSame(0, OpenItemRecord::query()->count());
        }
    }

    public function test_cross_tenant_settlement_source_is_rejected_without_history_write(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '100');
        $this->repository->append($item);
        $item->applySettlement($this->settlementId(1), $this->date('2026-01-15'), new Money('10', new Currency('EUR')), $this->journalEntryId(self::ADMINISTRATION_B, 2));
        $settlement = $item->settlement($this->settlementId(1));
        self::assertNotNull($settlement);

        try {
            $this->repository->appendSettlement($item, $settlement);
            self::fail('Cross-tenant settlement source must be rejected.');
        } catch (DomainException) {
            self::assertSame(0, OpenItemSettlementRecord::query()->count());
        }
    }

    public function test_duplicate_identities_and_history_rewrite_are_rejected(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '100');
        $this->repository->append($item);

        try {
            $this->repository->append($item);
            self::fail('Duplicate OpenItem identity must fail.');
        } catch (DomainException) {
            self::assertSame(1, OpenItemRecord::query()->count());
        }

        $settlement = $this->apply($item, 1, '2026-01-15', '10', 2);
        $this->repository->appendSettlement($item, $settlement);

        try {
            $this->repository->appendSettlement($item, $settlement);
            self::fail('Duplicate settlement identity must fail.');
        } catch (DomainException) {
            self::assertSame(1, OpenItemSettlementRecord::query()->count());
        }
    }

    public function test_database_composite_constraints_reject_cross_tenant_open_item_settlement(): void
    {
        $itemA = $this->openItem(self::ADMINISTRATION_A, '100');
        $this->repository->append($itemA);

        $this->expectException(QueryException::class);
        OpenItemSettlementRecord::query()->create([
            'id' => '50000000-0000-4000-8000-000000000001',
            'administration_id' => self::ADMINISTRATION_B,
            'open_item_id' => $itemA->id()->toString(),
            'effective_date' => '2026-01-15',
            'amount' => '10.12345678',
            'currency' => 'EUR',
            'source_journal_entry_id' => $this->journalEntryId(self::ADMINISTRATION_B, 2)->toString(),
            'type' => OpenItemSettlementType::Applied->value,
            'reversed_settlement_id' => null,
        ]);
    }

    public function test_reconstituted_items_feed_open_items_report_without_adapter_arithmetic(): void
    {
        $item = $this->openItem(self::ADMINISTRATION_A, '100');
        $this->repository->append($item);
        $settlement = $this->apply($item, 1, '2026-01-15', '40', 2);
        $this->repository->appendSettlement($item, $settlement);
        $asOf = $this->date('2026-01-31');

        $result = (new OpenItemsReport)->generate(
            $this->read(self::ADMINISTRATION_A, '2026-01-31'),
            $this->administrationId(self::ADMINISTRATION_A),
            new Currency('EUR'),
            $asOf,
        );

        self::assertSame('60', $result->totalOpenAmount()->amount());
        self::assertSame(OpenItemStatus::PartiallySettled, $result->lines()[0]->status());
    }

    public function test_contracts_are_bound_and_expose_no_update_or_delete(): void
    {
        self::assertInstanceOf(EloquentOpenItemRepository::class, $this->app->make(OpenItemReadRepository::class));
        self::assertInstanceOf(EloquentOpenItemRepository::class, $this->app->make(OpenItemStore::class));
        self::assertInstanceOf(EloquentOpenItemRepository::class, $this->app->make(OpenItemSettlementStore::class));
        self::assertFalse(method_exists(OpenItemStore::class, 'update'));
        self::assertFalse(method_exists(OpenItemStore::class, 'delete'));
        self::assertFalse(method_exists(OpenItemSettlementStore::class, 'update'));
        self::assertFalse(method_exists(OpenItemSettlementStore::class, 'delete'));
    }

    private function apply(OpenItem $item, int $id, string $date, string $amount, int $entry): OpenItemSettlement
    {
        $item->applySettlement(
            $this->settlementId($id),
            $this->date($date),
            new Money($amount, new Currency('EUR')),
            $this->journalEntryId($item->administrationId()->toString(), $entry),
        );
        $settlement = $item->settlement($this->settlementId($id));
        self::assertNotNull($settlement);

        return $settlement;
    }

    /** @return list<OpenItem> */
    private function read(string $administration, string $asOf): array
    {
        return $this->repository->findForAdministrationAsOf(
            $this->administrationId($administration),
            $this->date($asOf),
        );
    }

    private function openItem(
        string $administration,
        string $amount,
        string $openedOn = '2026-01-01',
        int $sequence = 1,
        OpenItemType $type = OpenItemType::Receivable,
    ): OpenItem {
        return new OpenItem(
            new OpenItemId(new Uuid(sprintf('30000000-0000-4000-8000-%012d', $sequence))),
            $this->administrationId($administration),
            new RelationId(new Uuid(sprintf('40000000-0000-4000-8000-%012d', $sequence))),
            $this->journalEntryId($administration, 1),
            $type,
            new Money($amount, new Currency('EUR')),
            $this->date($openedOn),
        );
    }

    private function createPostedEntry(string $administration, int $sequence): void
    {
        JournalEntryRecord::query()->create([
            'id' => $this->journalEntryId($administration, $sequence)->toString(),
            'administration_id' => $administration,
            'journal_id' => sprintf('70000000-0000-4000-8000-%012d', $sequence),
            'posting_date' => '2026-01-01',
            'reference' => 'OpenItem source '.$sequence,
            'status' => JournalEntryStatus::Posted->value,
        ]);
    }

    private function createAdministration(string $id, string $code): void
    {
        AdministrationRecord::query()->create([
            'id' => $id,
            'code' => $code,
            'name' => 'Administration '.$code,
            'base_currency' => 'EUR',
            'status' => 'active',
        ]);
    }

    private function journalEntryId(string $administration, int $sequence): JournalEntryId
    {
        $prefix = $administration === self::ADMINISTRATION_A ? '60000000' : '61000000';

        return new JournalEntryId(new Uuid(sprintf('%s-0000-4000-8000-%012d', $prefix, $sequence)));
    }

    private function settlementId(int $sequence): OpenItemSettlementId
    {
        return new OpenItemSettlementId(new Uuid(sprintf('50000000-0000-4000-8000-%012d', $sequence)));
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }
}

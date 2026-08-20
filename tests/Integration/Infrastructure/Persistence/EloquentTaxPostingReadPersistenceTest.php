<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Reporting\VatOverview;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxPostingRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxPostingRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentTaxPostingReadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private EloquentTaxPostingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTaxPostingRepository;
        $this->createAdministration(self::ADMINISTRATION_A, 'A');
        $this->createAdministration(self::ADMINISTRATION_B, 'B');
        $this->seedAccounting(self::ADMINISTRATION_A);
        $this->seedAccounting(self::ADMINISTRATION_B);
    }

    public function test_original_roundtrips_all_immutable_snapshot_and_audit_state(): void
    {
        $posting = $this->posting(1, self::ADMINISTRATION_A, '2026-08-20', taxRate: '9.25', taxableBase: '100.12345678', taxAmount: '9.26141975');
        $this->repository->append($posting);

        $read = $this->read(self::ADMINISTRATION_A, '2026-08-01', '2026-08-31')[0];

        self::assertTrue($posting->id()->equals($read->id()));
        self::assertTrue($posting->administrationId()->equals($read->administrationId()));
        self::assertTrue($posting->taxCodeId()->equals($read->taxCodeId()));
        self::assertSame('9.25', $read->taxRate()->value());
        self::assertSame('100.12345678', $read->taxableBase()->amount());
        self::assertSame('9.26141975', $read->taxAmount()->amount());
        self::assertSame('EUR', $read->taxAmount()->currency()->code());
        self::assertSame(TaxPostingDirection::Output, $read->direction());
        self::assertSame(TaxPostingType::Original, $read->type());
        self::assertSame(TaxSourceDocumentType::SalesInvoice, $read->sourceDocumentType());
        self::assertTrue($posting->sourceDocumentId()->equals($read->sourceDocumentId()));
        self::assertTrue($posting->sourceLineId()->equals($read->sourceLineId()));
        self::assertTrue($posting->journalEntryId()->equals($read->journalEntryId()));
        self::assertTrue($posting->baseJournalEntryLineId()->equals($read->baseJournalEntryLineId()));
        self::assertTrue($posting->taxJournalEntryLineId()?->equals($read->taxJournalEntryLineId()));
        self::assertNull($read->reversedTaxPostingId());
    }

    public function test_zero_percent_input_and_nullable_tax_line_roundtrip(): void
    {
        $posting = $this->posting(
            1,
            self::ADMINISTRATION_A,
            '2026-08-20',
            direction: TaxPostingDirection::Input,
            sourceType: TaxSourceDocumentType::PurchaseInvoice,
            taxRate: '0',
            taxableBase: '50',
            taxAmount: '0',
            includeTaxLine: false,
        );
        $this->repository->append($posting);
        $read = $this->read(self::ADMINISTRATION_A, '2026-08-20', '2026-08-20')[0];

        self::assertSame(TaxPostingDirection::Input, $read->direction());
        self::assertSame('0', $read->taxRate()->value());
        self::assertSame('0', $read->taxAmount()->amount());
        self::assertNull($read->taxJournalEntryLineId());
    }

    public function test_reversal_roundtrips_without_mutating_original_and_uses_own_period(): void
    {
        $original = $this->posting(1, self::ADMINISTRATION_A, '2026-08-15');
        $this->repository->append($original);
        $reversal = $this->posting(2, self::ADMINISTRATION_A, '2026-09-01', type: TaxPostingType::Reversal, reversed: $original->id(), entry: 2);
        $this->repository->append($reversal);

        $august = $this->read(self::ADMINISTRATION_A, '2026-08-01', '2026-08-31');
        $september = $this->read(self::ADMINISTRATION_A, '2026-09-01', '2026-09-30');

        self::assertCount(1, $august);
        self::assertSame(TaxPostingType::Original, $august[0]->type());
        self::assertNull($august[0]->reversedTaxPostingId());
        self::assertCount(1, $september);
        self::assertSame(TaxPostingType::Reversal, $september[0]->type());
        self::assertTrue($september[0]->reversedTaxPostingId()?->equals($original->id()));
        self::assertSame(2, TaxPostingRecord::query()->count());
    }

    public function test_period_boundaries_ordering_directions_types_and_tenants_are_preserved(): void
    {
        $postings = [
            $this->posting(5, self::ADMINISTRATION_A, '2026-08-31'),
            $this->posting(3, self::ADMINISTRATION_A, '2026-08-01', direction: TaxPostingDirection::Input),
            $this->posting(2, self::ADMINISTRATION_A, '2026-08-15'),
            $this->posting(1, self::ADMINISTRATION_A, '2026-08-15'),
            $this->posting(6, self::ADMINISTRATION_A, '2026-07-31'),
            $this->posting(7, self::ADMINISTRATION_A, '2026-09-01'),
            $this->posting(8, self::ADMINISTRATION_B, '2026-08-15'),
        ];
        foreach ($postings as $posting) {
            $this->repository->append($posting);
        }

        self::assertSame([3, 1, 2, 5], array_map(
            static fn (TaxPosting $posting): int => (int) substr($posting->id()->toString(), -1),
            $this->read(self::ADMINISTRATION_A, '2026-08-01', '2026-08-31'),
        ));
    }

    public function test_duplicate_identity_and_invalid_reversal_target_are_rejected(): void
    {
        $original = $this->posting(1, self::ADMINISTRATION_A, '2026-08-15');
        $this->repository->append($original);

        try {
            $this->repository->append($original);
            self::fail('Duplicate TaxPosting identity must fail.');
        } catch (DomainException) {
            self::assertSame(1, TaxPostingRecord::query()->count());
        }

        $this->expectException(DomainException::class);
        $this->repository->append($this->posting(
            2,
            self::ADMINISTRATION_A,
            '2026-09-01',
            type: TaxPostingType::Reversal,
            reversed: new TaxPostingId(new Uuid('90000000-0000-4000-8000-000000000099')),
            entry: 2,
        ));
    }

    public function test_cross_tenant_entry_base_and_tax_line_references_are_rejected(): void
    {
        $crossEntry = $this->posting(1, self::ADMINISTRATION_A, '2026-08-20', accountingAdministration: self::ADMINISTRATION_B);

        try {
            $this->repository->append($crossEntry);
            self::fail('Cross-tenant entry must fail.');
        } catch (DomainException) {
            self::assertSame(0, TaxPostingRecord::query()->count());
        }

        $crossBase = $this->posting(2, self::ADMINISTRATION_A, '2026-08-20', baseLineAdministration: self::ADMINISTRATION_B);
        try {
            $this->repository->append($crossBase);
            self::fail('Cross-tenant base line must fail.');
        } catch (DomainException) {
            self::assertSame(0, TaxPostingRecord::query()->count());
        }

        $this->expectException(DomainException::class);
        $this->repository->append($this->posting(3, self::ADMINISTRATION_A, '2026-08-20', taxLineAdministration: self::ADMINISTRATION_B));
    }

    public function test_database_rejects_cross_tenant_accounting_and_reversal_references(): void
    {
        $original = $this->posting(1, self::ADMINISTRATION_A, '2026-08-20');
        $this->repository->append($original);

        $attributes = TaxPostingRecord::query()->firstOrFail()->getAttributes();
        unset($attributes['created_at'], $attributes['updated_at']);
        $attributes['id'] = '80000000-0000-4000-8000-000000000002';
        $attributes['administration_id'] = self::ADMINISTRATION_B;
        $attributes['journal_entry_id'] = $this->journalEntryId(self::ADMINISTRATION_B, 1)->toString();
        $attributes['base_journal_entry_line_id'] = $this->lineId(self::ADMINISTRATION_B, 1, 1)->toString();
        $attributes['tax_journal_entry_line_id'] = $this->lineId(self::ADMINISTRATION_B, 1, 2)->toString();
        $attributes['reversed_tax_posting_id'] = $original->id()->toString();
        $attributes['type'] = TaxPostingType::Reversal->value;

        $this->expectException(QueryException::class);
        TaxPostingRecord::query()->create($attributes);
    }

    public function test_hydrated_facts_feed_vat_overview_without_repository_arithmetic(): void
    {
        $output = $this->posting(1, self::ADMINISTRATION_A, '2026-08-10', taxableBase: '100', taxAmount: '21');
        $input = $this->posting(2, self::ADMINISTRATION_A, '2026-08-11', direction: TaxPostingDirection::Input, sourceType: TaxSourceDocumentType::PurchaseInvoice, taxRate: '9', taxableBase: '100', taxAmount: '9', entry: 2);
        $zero = $this->posting(3, self::ADMINISTRATION_A, '2026-08-12', taxRate: '0', taxableBase: '50', taxAmount: '0', includeTaxLine: false, entry: 3);
        $reversal = $this->posting(4, self::ADMINISTRATION_A, '2026-08-20', sourceType: TaxSourceDocumentType::SalesCreditInvoice, type: TaxPostingType::Reversal, reversed: $output->id(), taxableBase: '100', taxAmount: '21', entry: 4);
        foreach ([$output, $input, $zero, $reversal] as $posting) {
            $this->repository->append($posting);
        }

        $result = (new VatOverview)->calculate(
            $this->read(self::ADMINISTRATION_A, '2026-08-01', '2026-08-31'),
            $this->administrationId(self::ADMINISTRATION_A),
            new Currency('EUR'),
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-31'),
        );

        self::assertCount(4, $result->lines());
        self::assertSame('0', $result->totalOutputTax()->amount());
        self::assertSame('9', $result->totalInputTax()->amount());
        self::assertSame('-9', $result->netVatPosition()->amount());
        self::assertCount(3, $result->taxCodeSummaries());
    }

    public function test_contracts_are_bound_and_append_only(): void
    {
        self::assertInstanceOf(EloquentTaxPostingRepository::class, $this->app->make(TaxPostingReadRepository::class));
        self::assertInstanceOf(EloquentTaxPostingRepository::class, $this->app->make(TaxPostingStore::class));
        self::assertFalse(method_exists(TaxPostingStore::class, 'update'));
        self::assertFalse(method_exists(TaxPostingStore::class, 'delete'));
        self::assertFalse(method_exists(TaxPostingStore::class, 'save'));
    }

    /** @return list<TaxPosting> */
    private function read(string $administration, string $start, string $end): array
    {
        return $this->repository->findForAdministrationAndPeriod(
            $this->administrationId($administration),
            $this->date($start),
            $this->date($end),
        );
    }

    private function posting(
        int $id,
        string $administration,
        string $date,
        TaxPostingDirection $direction = TaxPostingDirection::Output,
        TaxSourceDocumentType $sourceType = TaxSourceDocumentType::SalesInvoice,
        string $taxRate = '21',
        string $taxableBase = '100',
        string $taxAmount = '21',
        TaxPostingType $type = TaxPostingType::Original,
        ?TaxPostingId $reversed = null,
        bool $includeTaxLine = true,
        int $entry = 1,
        ?string $accountingAdministration = null,
        ?string $baseLineAdministration = null,
        ?string $taxLineAdministration = null,
    ): TaxPosting {
        $currency = new Currency('EUR');
        $entryAdministration = $accountingAdministration ?? $administration;

        return new TaxPosting(
            new TaxPostingId(new Uuid(sprintf('80000000-0000-4000-8000-%012d', $id))),
            $this->administrationId($administration),
            new TaxCodeId(new Uuid('81000000-0000-4000-8000-000000000001')),
            new TaxRate($taxRate),
            new Money($taxableBase, $currency),
            new Money($taxAmount, $currency),
            $direction,
            $sourceType,
            new TaxSourceDocumentId(new Uuid(sprintf('82000000-0000-4000-8000-%012d', $id))),
            new TaxSourceLineId(new Uuid(sprintf('83000000-0000-4000-8000-%012d', $id))),
            $this->date($date),
            $this->journalEntryId($entryAdministration, $entry),
            $this->lineId($baseLineAdministration ?? $entryAdministration, $entry, 1),
            $includeTaxLine ? $this->lineId($taxLineAdministration ?? $entryAdministration, $entry, 2) : null,
            $type,
            $reversed,
        );
    }

    private function seedAccounting(string $administration): void
    {
        $accountId = $administration === self::ADMINISTRATION_A
            ? '30000000-0000-4000-8000-000000000001'
            : '31000000-0000-4000-8000-000000000001';
        LedgerAccountRecord::query()->create([
            'id' => $accountId,
            'administration_id' => $administration,
            'code' => 'VAT',
            'name' => 'VAT account',
            'type' => 'liability',
            'status' => 'active',
        ]);

        for ($entry = 1; $entry <= 8; $entry++) {
            JournalEntryRecord::query()->create([
                'id' => $this->journalEntryId($administration, $entry)->toString(),
                'administration_id' => $administration,
                'journal_id' => sprintf('70000000-0000-4000-8000-%012d', $entry),
                'posting_date' => '2026-08-01',
                'reference' => 'Tax source '.$entry,
                'status' => 'posted',
            ]);
            foreach ([1, 2] as $line) {
                JournalEntryLineRecord::query()->create([
                    'id' => $this->lineId($administration, $entry, $line)->toString(),
                    'administration_id' => $administration,
                    'journal_entry_id' => $this->journalEntryId($administration, $entry)->toString(),
                    'ledger_account_id' => $accountId,
                    'debit_amount' => $line === 1 ? '100' : null,
                    'credit_amount' => $line === 2 ? '100' : null,
                    'currency' => 'EUR',
                    'description' => 'Tax line '.$line,
                ]);
            }
        }
    }

    private function journalEntryId(string $administration, int $entry): JournalEntryId
    {
        $prefix = $administration === self::ADMINISTRATION_A ? '60000000' : '61000000';

        return new JournalEntryId(new Uuid(sprintf('%s-0000-4000-8000-%012d', $prefix, $entry)));
    }

    private function lineId(string $administration, int $entry, int $line): JournalEntryLineId
    {
        $prefix = $administration === self::ADMINISTRATION_A ? '50000000' : '51000000';

        return new JournalEntryLineId(new Uuid(sprintf('%s-0000-4000-8%03d-%012d', $prefix, $entry, $line)));
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

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }
}

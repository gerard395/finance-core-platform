<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentJournalPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const A = '91000000-0000-4000-8000-000000000001';

    private const B = '92000000-0000-4000-8000-000000000001';

    private EloquentJournalRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentJournalRepository;
        $this->administration(self::A, 'A');
        $this->administration(self::B, 'B');
    }

    public function test_exact_state_roundtrips_and_reads_are_tenant_scoped(): void
    {
        $journal = $this->journal(1, 'SALES', JournalType::Sales, JournalStatus::Active);
        $this->repository->save($this->admin(self::A), $journal);
        $read = $this->repository->findByIdForAdministration($this->admin(self::A), $journal->id());

        self::assertNotNull($read);
        self::assertSame('SALES', $read->code()->value());
        self::assertSame('Journal SALES', $read->name()->value());
        self::assertSame(JournalType::Sales, $read->type());
        self::assertSame(JournalStatus::Active, $read->status());
        self::assertNull($this->repository->findByIdForAdministration($this->admin(self::B), $journal->id()));
        self::assertCount(1, $this->repository->findForAdministration($this->admin(self::A)));
        self::assertSame([], $this->repository->findForAdministration($this->admin(self::B)));

        $journal->rename(new JournalName('Domestic sales'));
        $journal->deactivate();
        $this->repository->save($this->admin(self::A), $journal);
        $changed = $this->repository->findByIdForAdministration($this->admin(self::A), $journal->id());
        self::assertSame('Domestic sales', $changed?->name()->value());
        self::assertSame(JournalStatus::Inactive, $changed?->status());
        self::assertSame(JournalType::Sales, $changed?->type());
    }

    public function test_same_code_is_allowed_across_tenants_but_rejected_within_one_tenant(): void
    {
        $this->repository->save($this->admin(self::A), $this->journal(1, 'GENERAL', JournalType::General, JournalStatus::Active));
        $this->repository->save($this->admin(self::B), $this->journal(2, 'GENERAL', JournalType::General, JournalStatus::Inactive));
        self::assertCount(1, $this->repository->findForAdministration($this->admin(self::A)));
        self::assertCount(1, $this->repository->findForAdministration($this->admin(self::B)));

        $this->expectException(QueryException::class);
        $this->repository->save($this->admin(self::A), $this->journal(3, 'GENERAL', JournalType::Bank, JournalStatus::Active));
    }

    public function test_identity_cannot_move_to_another_administration(): void
    {
        $journal = $this->journal(1, 'SALES', JournalType::Sales, JournalStatus::Active);
        $this->repository->save($this->admin(self::A), $journal);

        $this->expectException(DomainException::class);
        $this->repository->save($this->admin(self::B), $journal);
    }

    public function test_journal_entry_composite_fk_rejects_cross_tenant_and_delete_is_restricted(): void
    {
        $journal = $this->journal(1, 'SALES', JournalType::Sales, JournalStatus::Active);
        $this->repository->save($this->admin(self::A), $journal);
        JournalEntryRecord::query()->create(['id' => '93000000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'journal_id' => $journal->id()->toString(), 'posting_date' => '2026-08-24', 'reference' => 'JE-1', 'status' => 'posted']);
        self::assertSame($journal->id()->toString(), JournalEntryRecord::query()->firstOrFail()->getAttribute('journal_id'));

        try {
            JournalEntryRecord::query()->create(['id' => '93000000-0000-4000-8000-000000000002', 'administration_id' => self::B, 'journal_id' => $journal->id()->toString(), 'posting_date' => '2026-08-24', 'reference' => 'JE-2', 'status' => 'posted']);
            self::fail('Cross-tenant JournalEntry reference was accepted.');
        } catch (QueryException) {
            self::assertDatabaseMissing('journal_entries', ['id' => '93000000-0000-4000-8000-000000000002']);
        }

        $this->expectException(QueryException::class);
        JournalRecord::query()->whereKey($journal->id()->toString())->delete();
    }

    public function test_application_contracts_are_bound_and_expose_no_delete_api(): void
    {
        self::assertInstanceOf(EloquentJournalRepository::class, $this->app->make(JournalReadRepository::class));
        self::assertInstanceOf(EloquentJournalRepository::class, $this->app->make(JournalStore::class));
        self::assertFalse(method_exists(JournalStore::class, 'delete'));
        self::assertFalse(method_exists(EloquentJournalRepository::class, 'delete'));
    }

    private function journal(int $sequence, string $code, JournalType $type, JournalStatus $status): Journal
    {
        return new Journal(new JournalId(new Uuid(sprintf('94000000-0000-4000-8000-%012d', $sequence))), new JournalCode($code), new JournalName('Journal '.$code), $type, $status);
    }

    private function administration(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => 'JR-'.$suffix, 'name' => 'Journal tenant '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}

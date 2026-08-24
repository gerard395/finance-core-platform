<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Development;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioningConflict;
use App\Application\Sales\SalesPostingConfiguration;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationStore;
use App\Application\Sales\SalesPostingConfigurationWriteResult;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Development\DemoAccountingProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class DemoAccountingProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '10000000-0000-4000-8000-000000000001';

    private const string B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->administration(self::A, 'DEVA');
        $this->administration(self::B, 'DEVB');
    }

    public function test_empty_administration_receives_exact_demo_master_data_and_configuration(): void
    {
        $result = $this->provisioner()->provision($this->id(self::A));

        self::assertSame('VERK', $result->salesJournal->code()->value());
        self::assertSame('Verkoop', $result->salesJournal->name()->value());
        self::assertSame(JournalType::Sales, $result->salesJournal->type());
        self::assertSame(JournalStatus::Active, $result->salesJournal->status());
        self::assertSame([
            ['code' => '1300', 'name' => 'Debiteuren', 'type' => 'asset', 'status' => 'active'],
            ['code' => '1600', 'name' => 'Af te dragen btw', 'type' => 'liability', 'status' => 'active'],
            ['code' => '8000', 'name' => 'Omzet', 'type' => 'revenue', 'status' => 'active'],
        ], DB::table('ledger_accounts')->where('administration_id', self::A)->orderBy('code')->get(['code', 'name', 'type', 'status'])->map(static fn (object $row): array => (array) $row)->all());
        $this->assertConfiguration($result->salesJournal->id(), $result->accountsReceivable->id(), $result->revenue->id(), $result->outputVat->id());
        self::assertSame(0, DB::table('journals')->where('administration_id', self::B)->count());
        self::assertSame(0, DB::table('ledger_accounts')->where('administration_id', self::B)->count());
        self::assertSame(0, DB::table('sales_posting_configurations')->where('administration_id', self::B)->count());
    }

    public function test_second_run_preserves_ids_state_and_timestamps_without_duplicates(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 10:00:00');
        $first = $this->provisioner()->provision($this->id(self::A));
        $before = $this->rows();

        CarbonImmutable::setTestNow('2026-08-25 10:00:00');
        $second = $this->provisioner()->provision($this->id(self::A));

        self::assertSame($before, $this->rows());
        self::assertTrue($first->salesJournal->id()->equals($second->salesJournal->id()));
        self::assertTrue($first->accountsReceivable->id()->equals($second->accountsReceivable->id()));
        self::assertSame(1, DB::table('journals')->where('administration_id', self::A)->count());
        self::assertSame(3, DB::table('ledger_accounts')->where('administration_id', self::A)->count());
        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
    }

    public function test_unrelated_master_data_is_preserved_and_other_tenant_is_untouched(): void
    {
        $journals = $this->app->make(JournalStore::class);
        $accounts = $this->app->make(LedgerAccountStore::class);
        $journals->save($this->id(self::A), $this->journal('30000000-0000-4000-8000-000000000001', 'MEMO', 'Memoriaal', JournalType::General));
        $accounts->save($this->id(self::A), $this->account('30000000-0000-4000-8000-000000000002', '9999', 'Onverwant', LedgerAccountType::Expense));
        $journals->save($this->id(self::B), $this->journal('30000000-0000-4000-8000-000000000003', 'BANK', 'Bank', JournalType::Bank));
        $beforeB = DB::table('journals')->where('administration_id', self::B)->get()->map(static fn (object $row): array => (array) $row)->all();

        $this->provisioner()->provision($this->id(self::A));

        self::assertSame(['MEMO', 'VERK'], DB::table('journals')->where('administration_id', self::A)->orderBy('code')->pluck('code')->all());
        self::assertSame(['1300', '1600', '8000', '9999'], DB::table('ledger_accounts')->where('administration_id', self::A)->orderBy('code')->pluck('code')->all());
        self::assertSame($beforeB, DB::table('journals')->where('administration_id', self::B)->get()->map(static fn (object $row): array => (array) $row)->all());
    }

    public function test_conflicting_code_stops_atomically_without_overwrite(): void
    {
        $conflict = $this->journal('30000000-0000-4000-8000-000000000010', 'VERK', 'Eigen verkoopboek', JournalType::Sales);
        $this->app->make(JournalStore::class)->save($this->id(self::A), $conflict);

        try {
            $this->provisioner()->provision($this->id(self::A));
            self::fail('A factual collision must stop provisioning.');
        } catch (DevelopmentAccountingMasterDataProvisioningConflict) {
            self::assertTrue(true);
        }

        self::assertSame('Eigen verkoopboek', DB::table('journals')->where('administration_id', self::A)->value('name'));
        self::assertSame(0, DB::table('ledger_accounts')->where('administration_id', self::A)->count());
        self::assertSame(0, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
    }

    public function test_late_configuration_failure_rolls_back_all_master_data(): void
    {
        $provisioner = new DemoAccountingProvisioner(
            $this->app->make(TransactionManager::class),
            $this->app->make(JournalReadRepository::class),
            $this->app->make(JournalStore::class),
            $this->app->make(LedgerAccountReadRepository::class),
            $this->app->make(LedgerAccountStore::class),
            $this->app->make(SalesPostingConfigurationReader::class),
            new RejectingConfigurationStore,
        );

        $this->expectException(DevelopmentAccountingMasterDataProvisioningConflict::class);
        try {
            $provisioner->provision($this->id(self::A));
        } finally {
            self::assertSame(0, DB::table('journals')->where('administration_id', self::A)->count());
            self::assertSame(0, DB::table('ledger_accounts')->where('administration_id', self::A)->count());
            self::assertSame(0, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
        }
    }

    public function test_outer_transaction_rollback_removes_complete_provisioning(): void
    {
        try {
            $this->app->make(TransactionManager::class)->run(function (): void {
                $this->provisioner()->provision($this->id(self::A));
                throw new RuntimeException('Force outer rollback.');
            });
        } catch (RuntimeException) {
            self::assertSame(0, DB::table('journals')->where('administration_id', self::A)->count());
            self::assertSame(0, DB::table('ledger_accounts')->where('administration_id', self::A)->count());
            self::assertSame(0, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
        }
    }

    private function provisioner(): DevelopmentAccountingMasterDataProvisioner
    {
        return $this->app->make(DevelopmentAccountingMasterDataProvisioner::class);
    }

    private function rows(): array
    {
        return [
            DB::table('journals')->where('administration_id', self::A)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            DB::table('ledger_accounts')->where('administration_id', self::A)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            DB::table('sales_posting_configurations')->where('administration_id', self::A)->get()->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function assertConfiguration(JournalId $journal, LedgerAccountId $ar, LedgerAccountId $revenue, LedgerAccountId $vat): void
    {
        $this->assertDatabaseHas('sales_posting_configurations', [
            'administration_id' => self::A,
            'sales_journal_id' => $journal->toString(),
            'accounts_receivable_ledger_account_id' => $ar->toString(),
            'revenue_ledger_account_id' => $revenue->toString(),
            'output_vat_ledger_account_id' => $vat->toString(),
        ]);
    }

    private function journal(string $id, string $code, string $name, JournalType $type): Journal
    {
        return new Journal(new JournalId(new Uuid($id)), new JournalCode($code), new JournalName($name), $type, JournalStatus::Active);
    }

    private function account(string $id, string $code, string $name, LedgerAccountType $type): LedgerAccount
    {
        return new LedgerAccount(new LedgerAccountId(new Uuid($id)), new LedgerAccountCode($code), new LedgerAccountName($name), $type, LedgerAccountStatus::Active);
    }

    private function administration(string $id, string $code): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => $code, 'name' => 'Development '.$code, 'base_currency' => 'EUR', 'status' => 'active']);
    }

    private function id(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}

final class RejectingConfigurationStore implements SalesPostingConfigurationStore
{
    public function save(SalesPostingConfiguration $configuration): SalesPostingConfigurationWriteResult
    {
        return SalesPostingConfigurationWriteResult::InvalidReference;
    }
}

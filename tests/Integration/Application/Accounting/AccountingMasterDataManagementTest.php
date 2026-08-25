<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\AccountingMasterDataWriteResult;
use App\Application\Accounting\CreateJournal;
use App\Application\Accounting\CreateLedgerAccount;
use App\Application\Accounting\GetJournalMasterData;
use App\Application\Accounting\GetLedgerAccountMasterData;
use App\Application\Accounting\SetJournalStatus;
use App\Application\Accounting\SetLedgerAccountStatus;
use App\Application\Accounting\UpdateJournal;
use App\Application\Accounting\UpdateLedgerAccount;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class AccountingMasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '81000000-0000-4000-8000-000000000001';

    private const string B = '82000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        DB::table('administrations')->insert([
            ['id' => self::A, 'code' => 'MDA', 'name' => 'Masterdata A', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::B, 'code' => 'MDB', 'name' => 'Masterdata B', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_journal_lifecycle_is_tenant_scoped_with_immutable_code_and_type(): void
    {
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(CreateJournal::class)->execute($this->id(self::A), 'sale', 'Verkoop', JournalType::Sales));
        self::assertSame(AccountingMasterDataWriteResult::DuplicateCode, $this->app->make(CreateJournal::class)->execute($this->id(self::A), 'SALE', 'Duplicaat', JournalType::General));
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(CreateJournal::class)->execute($this->id(self::B), 'SALE', 'Tenant B', JournalType::General));
        $journal = $this->app->make(GetJournalMasterData::class)->list($this->id(self::A))[0];

        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(UpdateJournal::class)->execute($this->id(self::A), $journal->id(), 'Nieuwe naam'));
        self::assertSame(AccountingMasterDataWriteResult::NotFound, $this->app->make(UpdateJournal::class)->execute($this->id(self::B), $journal->id(), 'Forged'));
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(SetJournalStatus::class)->execute($this->id(self::A), $journal->id(), JournalStatus::Inactive));
        $updated = $this->app->make(GetJournalMasterData::class)->find($this->id(self::A), $journal->id());
        self::assertNotNull($updated);
        self::assertSame('SALE', $updated->code()->value());
        self::assertSame(JournalType::Sales, $updated->type());
        self::assertSame('Nieuwe naam', $updated->name()->value());
        self::assertSame(JournalStatus::Inactive, $updated->status());
    }

    public function test_ledger_account_lifecycle_is_tenant_scoped_with_immutable_code_and_type(): void
    {
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(CreateLedgerAccount::class)->execute($this->id(self::A), '1300', 'Debiteuren', LedgerAccountType::Asset));
        self::assertSame(AccountingMasterDataWriteResult::DuplicateCode, $this->app->make(CreateLedgerAccount::class)->execute($this->id(self::A), '1300', 'Duplicaat', LedgerAccountType::Revenue));
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(CreateLedgerAccount::class)->execute($this->id(self::B), '1300', 'Tenant B', LedgerAccountType::Revenue));
        $account = $this->app->make(GetLedgerAccountMasterData::class)->list($this->id(self::A))[0];

        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(UpdateLedgerAccount::class)->execute($this->id(self::A), $account->id(), 'Handelsdebiteuren'));
        self::assertSame(AccountingMasterDataWriteResult::NotFound, $this->app->make(UpdateLedgerAccount::class)->execute($this->id(self::B), $account->id(), 'Forged'));
        self::assertSame(AccountingMasterDataWriteResult::Success, $this->app->make(SetLedgerAccountStatus::class)->execute($this->id(self::A), $account->id(), LedgerAccountStatus::Inactive));
        $updated = $this->app->make(GetLedgerAccountMasterData::class)->find($this->id(self::A), $account->id());
        self::assertNotNull($updated);
        self::assertSame('1300', $updated->code()->value());
        self::assertSame(LedgerAccountType::Asset, $updated->type());
        self::assertSame('Handelsdebiteuren', $updated->name()->value());
        self::assertSame(LedgerAccountStatus::Inactive, $updated->status());
    }

    public function test_input_validation_is_typed_and_lists_are_deterministic(): void
    {
        self::assertSame(AccountingMasterDataWriteResult::InvalidInput, $this->app->make(CreateJournal::class)->execute($this->id(self::A), '!', 'x', JournalType::Sales));
        self::assertSame(AccountingMasterDataWriteResult::InvalidInput, $this->app->make(CreateLedgerAccount::class)->execute($this->id(self::A), '!', 'x', LedgerAccountType::Asset));
        $create = $this->app->make(CreateLedgerAccount::class);
        self::assertSame(AccountingMasterDataWriteResult::Success, $create->execute($this->id(self::A), '8000', 'Omzet', LedgerAccountType::Revenue));
        self::assertSame(AccountingMasterDataWriteResult::Success, $create->execute($this->id(self::A), '1300', 'Debiteuren', LedgerAccountType::Asset));
        self::assertSame(['1300', '8000'], array_map(static fn ($account): string => $account->code()->value(), $this->app->make(GetLedgerAccountMasterData::class)->list($this->id(self::A))));
    }

    public function test_real_mysql_concurrent_duplicate_journal_code_has_exactly_one_durable_row(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the masterdata concurrency test.');
        }
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'journal-create-'), tempnam(sys_get_temp_dir(), 'journal-create-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(CreateJournal::class)->execute($this->id(self::A), 'RACE', 'Concurrent', JournalType::General);
                    file_put_contents($file, $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame('Success', $results[1]);
        self::assertContains($results[0], ['DuplicateCode', 'PersistenceConflict']);
        self::assertSame(1, DB::table('journals')->where('administration_id', self::A)->where('code', 'RACE')->count());
        DB::table('journals')->where('administration_id', self::A)->delete();
        DB::table('administrations')->whereIn('id', [self::A, self::B])->delete();
        DB::beginTransaction();
    }

    public function test_real_mysql_concurrent_duplicate_account_code_has_exactly_one_durable_row(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the masterdata concurrency test.');
        }
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'account-create-'), tempnam(sys_get_temp_dir(), 'account-create-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(CreateLedgerAccount::class)->execute($this->id(self::A), 'RACE', 'Concurrent', LedgerAccountType::Expense);
                    file_put_contents($file, $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame('Success', $results[1]);
        self::assertContains($results[0], ['DuplicateCode', 'PersistenceConflict']);
        self::assertSame(1, DB::table('ledger_accounts')->where('administration_id', self::A)->where('code', 'RACE')->count());
        DB::table('ledger_accounts')->where('administration_id', self::A)->delete();
        DB::table('administrations')->whereIn('id', [self::A, self::B])->delete();
        DB::beginTransaction();
    }

    private function id(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\GetSalesPostingConfigurationSettings;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationReadStatus;
use App\Application\Sales\UpdateSalesPostingConfiguration;
use App\Application\Sales\UpdateSalesPostingConfigurationResult;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UpdateSalesPostingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '11000000-0000-4000-8000-000000000001';

    private const string B = '22000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterData();
    }

    public function test_valid_configuration_is_created_updated_and_readiness_is_success(): void
    {
        $result = $this->update()->execute($this->administration(), $this->journal(1), $this->account(1), $this->account(2), $this->account(3));

        self::assertSame(UpdateSalesPostingConfigurationResult::Saved, $result);
        self::assertSame(SalesPostingConfigurationReadStatus::Success, $this->reader()->read($this->administration())->status());

        $updated = $this->update()->execute($this->administration(), $this->journal(1), $this->account(1), $this->account(4), $this->account(3));
        self::assertSame(UpdateSalesPostingConfigurationResult::Saved, $updated);
        $this->assertDatabaseHas('sales_posting_configurations', [
            'administration_id' => self::A,
            'revenue_ledger_account_id' => $this->account(4)->toString(),
        ]);
        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
    }

    #[DataProvider('invalidReferenceProvider')]
    public function test_invalid_reference_is_typed_and_preserves_complete_old_configuration(string $reference, string $value): void
    {
        self::assertSame(
            UpdateSalesPostingConfigurationResult::Saved,
            $this->update()->execute($this->administration(), $this->journal(1), $this->account(1), $this->account(2), $this->account(3)),
        );
        $before = (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first();
        $arguments = [$this->journal(1), $this->account(1), $this->account(2), $this->account(3)];
        $arguments[array_search($reference, ['journal', 'ar', 'revenue', 'vat'], true)] = str_starts_with($reference, 'journal') || $reference === 'journal'
            ? new JournalId(new Uuid($value))
            : new LedgerAccountId(new Uuid($value));

        $result = $this->update()->execute($this->administration(), ...$arguments);

        self::assertSame(UpdateSalesPostingConfigurationResult::InvalidReference, $result);
        self::assertSame($before, (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first());
    }

    public static function invalidReferenceProvider(): array
    {
        return [
            'missing journal' => ['journal', '33000000-0000-4000-8000-000000000099'],
            'inactive journal' => ['journal', '33000000-0000-4000-8000-000000000002'],
            'wrong journal type' => ['journal', '33000000-0000-4000-8000-000000000003'],
            'cross tenant journal' => ['journal', '44000000-0000-4000-8000-000000000001'],
            'missing account' => ['ar', '55000000-0000-4000-8000-000000000099'],
            'inactive account' => ['ar', '55000000-0000-4000-8000-000000000005'],
            'wrong AR type' => ['ar', '55000000-0000-4000-8000-000000000002'],
            'wrong revenue type' => ['revenue', '55000000-0000-4000-8000-000000000001'],
            'wrong VAT type' => ['vat', '55000000-0000-4000-8000-000000000002'],
            'cross tenant account' => ['vat', '66000000-0000-4000-8000-000000000001'],
        ];
    }

    public function test_settings_query_exposes_only_active_type_compatible_same_tenant_options(): void
    {
        $settings = $this->app->make(GetSalesPostingConfigurationSettings::class)->execute($this->administration());

        self::assertSame(['SALES'], array_map(static fn ($journal): string => $journal->code()->value(), $settings->salesJournals));
        self::assertSame(['AR'], array_map(static fn ($account): string => $account->code()->value(), $settings->accountsReceivableAccounts));
        self::assertSame(['REV', 'REV2'], array_map(static fn ($account): string => $account->code()->value(), $settings->revenueAccounts));
        self::assertSame(['VAT'], array_map(static fn ($account): string => $account->code()->value(), $settings->outputVatAccounts));
        self::assertSame(SalesPostingConfigurationReadStatus::Missing, $settings->current->status());
    }

    private function masterData(): void
    {
        $now = now();
        DB::table('administrations')->insert([
            ['id' => self::A, 'code' => 'SETA', 'name' => 'Settings A', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::B, 'code' => 'SETB', 'name' => 'Settings B', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('journals')->insert([
            $this->journalRow(self::A, 1, 'SALES', 'sales', 'active'),
            $this->journalRow(self::A, 2, 'INACTIVE', 'sales', 'inactive'),
            $this->journalRow(self::A, 3, 'GENERAL', 'general', 'active'),
            ['id' => '44000000-0000-4000-8000-000000000001', 'administration_id' => self::B, 'code' => 'BSALES', 'name' => 'B Sales', 'type' => 'sales', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('ledger_accounts')->insert([
            $this->accountRow(self::A, 1, 'AR', 'asset', 'active'),
            $this->accountRow(self::A, 2, 'REV', 'revenue', 'active'),
            $this->accountRow(self::A, 3, 'VAT', 'liability', 'active'),
            $this->accountRow(self::A, 4, 'REV2', 'revenue', 'active'),
            $this->accountRow(self::A, 5, 'OLDAR', 'asset', 'inactive'),
            ['id' => '66000000-0000-4000-8000-000000000001', 'administration_id' => self::B, 'code' => 'BLIAB', 'name' => 'B Liability', 'type' => 'liability', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function journalRow(string $administration, int $sequence, string $code, string $type, string $status): array
    {
        return ['id' => $this->journal($sequence)->toString(), 'administration_id' => $administration, 'code' => $code, 'name' => $code.' name', 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function accountRow(string $administration, int $sequence, string $code, string $type, string $status): array
    {
        return ['id' => $this->account($sequence)->toString(), 'administration_id' => $administration, 'code' => $code, 'name' => $code.' name', 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function update(): UpdateSalesPostingConfiguration
    {
        return $this->app->make(UpdateSalesPostingConfiguration::class);
    }

    private function reader(): SalesPostingConfigurationReader
    {
        return $this->app->make(SalesPostingConfigurationReader::class);
    }

    private function administration(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::A));
    }

    private function journal(int $sequence): JournalId
    {
        return new JournalId(new Uuid(sprintf('33000000-0000-4000-8000-%012d', $sequence)));
    }

    private function account(int $sequence): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid(sprintf('55000000-0000-4000-8000-%012d', $sequence)));
    }
}

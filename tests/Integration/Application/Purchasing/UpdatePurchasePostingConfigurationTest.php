<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Purchasing\GetPurchasePostingConfigurationSettings;
use App\Application\Purchasing\PurchasePostingConfigurationInvalidReference;
use App\Application\Purchasing\PurchasePostingConfigurationReader;
use App\Application\Purchasing\PurchasePostingConfigurationReadStatus;
use App\Application\Purchasing\UpdatePurchasePostingConfiguration;
use App\Application\Purchasing\UpdatePurchasePostingConfigurationResult;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UpdatePurchasePostingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '81000000-0000-4000-8000-000000000001';

    private const string B = '82000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        foreach ([[self::A, 'PA'], [self::B, 'PB']] as [$id, $code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert([
            $this->journalRow(self::A, 1, 'PUR', 'purchase', 'active'),
            $this->journalRow(self::A, 2, 'OLD', 'purchase', 'inactive'),
            $this->journalRow(self::A, 3, 'SALE', 'sales', 'active'),
            $this->journalRow(self::B, 4, 'BPUR', 'purchase', 'active'),
        ]);
        DB::table('ledger_accounts')->insert([
            $this->accountRow(self::A, 1, 'AP', 'liability', 'active'),
            $this->accountRow(self::A, 2, 'VAT', 'asset', 'active'),
            $this->accountRow(self::A, 3, 'EXP', 'expense', 'active'),
            $this->accountRow(self::A, 4, 'OLDAP', 'liability', 'inactive'),
            $this->accountRow(self::B, 5, 'BAP', 'liability', 'active'),
        ]);
    }

    public function test_missing_create_update_success_and_filtered_settings(): void
    {
        self::assertSame(PurchasePostingConfigurationReadStatus::Missing, $this->reader()->read($this->admin())->status);
        self::assertSame(UpdatePurchasePostingConfigurationResult::Saved, $this->update()->execute($this->admin(), $this->journal(1), $this->account(1), $this->account(2)));
        self::assertSame(PurchasePostingConfigurationReadStatus::Success, $this->reader()->read($this->admin())->status);
        $settings = $this->app->make(GetPurchasePostingConfigurationSettings::class)->execute($this->admin());
        self::assertSame(['PUR'], array_map(static fn ($item): string => $item->code()->value(), $settings->purchaseJournals));
        self::assertSame(['AP'], array_map(static fn ($item): string => $item->code()->value(), $settings->accountsPayableAccounts));
        self::assertSame(['VAT'], array_map(static fn ($item): string => $item->code()->value(), $settings->inputVatAccounts));
    }

    public function test_wrong_type_inactive_and_cross_tenant_references_preserve_old_configuration(): void
    {
        $this->update()->execute($this->admin(), $this->journal(1), $this->account(1), $this->account(2));
        $before = (array) DB::table('purchase_posting_configurations')->where('administration_id', self::A)->first();
        foreach ([
            [$this->journal(3), $this->account(1), $this->account(2)],
            [$this->journal(2), $this->account(1), $this->account(2)],
            [$this->journal(1), $this->account(2), $this->account(2)],
            [$this->journal(1), $this->account(4), $this->account(2)],
            [$this->journal(1), $this->account(1), $this->account(5)],
        ] as $arguments) {
            self::assertSame(UpdatePurchasePostingConfigurationResult::InvalidReference, $this->update()->execute($this->admin(), ...$arguments));
            self::assertSame($before, (array) DB::table('purchase_posting_configurations')->where('administration_id', self::A)->first());
        }
    }

    public function test_deactivation_reports_exact_invalid_reference(): void
    {
        $this->update()->execute($this->admin(), $this->journal(1), $this->account(1), $this->account(2));
        DB::table('ledger_accounts')->where('id', $this->account(2)->toString())->update(['status' => 'inactive']);
        $result = $this->reader()->read($this->admin());
        self::assertSame(PurchasePostingConfigurationReadStatus::InvalidReference, $result->status);
        self::assertSame([PurchasePostingConfigurationInvalidReference::InputVat], $result->invalidReferences);
    }

    private function journalRow(string $admin, int $n, string $code, string $type, string $status): array
    {
        return ['id' => $this->journal($n)->toString(), 'administration_id' => $admin, 'code' => $code, 'name' => $code, 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function accountRow(string $admin, int $n, string $code, string $type, string $status): array
    {
        return ['id' => $this->account($n)->toString(), 'administration_id' => $admin, 'code' => $code, 'name' => $code, 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::A));
    }

    private function journal(int $n): JournalId
    {
        return new JournalId(new Uuid(sprintf('83000000-0000-4000-8000-%012d', $n)));
    }

    private function account(int $n): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid(sprintf('84000000-0000-4000-8000-%012d', $n)));
    }

    private function update(): UpdatePurchasePostingConfiguration
    {
        return $this->app->make(UpdatePurchasePostingConfiguration::class);
    }

    private function reader(): PurchasePostingConfigurationReader
    {
        return $this->app->make(PurchasePostingConfigurationReader::class);
    }
}

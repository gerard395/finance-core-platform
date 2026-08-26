<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\AdministrationBankAccountWriteResult;
use App\Application\Banking\BankingPostingConfigurationInvalidReference;
use App\Application\Banking\BankingPostingConfigurationReader;
use App\Application\Banking\BankingPostingConfigurationReadStatus;
use App\Application\Banking\GetBankingSettings;
use App\Application\Banking\ManageAdministrationBankAccounts;
use App\Application\Banking\UpdateBankingPostingConfiguration;
use App\Application\Banking\UpdateBankingPostingConfigurationResult;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Banking\Enums\AdministrationBankAccountStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankAccountLabel;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BankingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string A = 'b2100000-0000-4000-8000-000000000001';

    private const string B = 'b2100000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([[self::A, 'BA'], [self::B, 'BB']] as [$id, $code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('journals')->insert([$this->journalRow(self::A, 1, 'BANK', 'bank', 'active'), $this->journalRow(self::A, 2, 'SALE', 'sales', 'active'), $this->journalRow(self::A, 3, 'OLD', 'bank', 'inactive'), $this->journalRow(self::B, 4, 'BBANK', 'bank', 'active')]);
        DB::table('ledger_accounts')->insert([$this->accountRow(self::A, 1, 'BANK', 'asset', 'active'), $this->accountRow(self::A, 2, 'AP', 'liability', 'active'), $this->accountRow(self::A, 3, 'OLD', 'asset', 'inactive'), $this->accountRow(self::B, 4, 'BBANK', 'asset', 'active')]);
    }

    public function test_operational_account_lifecycle_is_canonical_eur_tenant_owned_and_identity_immutable(): void
    {
        [$result, $id] = $this->manager()->create($this->admin(self::A), new Iban('nl91abna0417164300'), null, new AccountName('Holder'), new BankAccountLabel('Main'));
        self::assertSame(AdministrationBankAccountWriteResult::Success, $result);
        $account = $this->repository()->find($this->admin(self::A), $id);
        self::assertSame('NL91ABNA0417164300', $account?->iban()->value());
        self::assertSame('EUR', $account?->currency()->code());
        self::assertNull($this->repository()->find($this->admin(self::B), $id));

        [$duplicate] = $this->manager()->create($this->admin(self::A), new Iban('NL91ABNA0417164300'), null, new AccountName('Other'), new BankAccountLabel('Other'));
        self::assertSame(AdministrationBankAccountWriteResult::DuplicateIban, $duplicate);
        self::assertSame(AdministrationBankAccountWriteResult::Success, $this->manager()->update($this->admin(self::A), $id, new AccountName('Renamed'), new BankAccountLabel('Updated')));
        self::assertSame('NL91ABNA0417164300', $this->repository()->find($this->admin(self::A), $id)?->iban()->value());
        self::assertSame(AdministrationBankAccountWriteResult::Success, $this->manager()->setActive($this->admin(self::A), $id, false));
        self::assertFalse($this->repository()->find($this->admin(self::A), $id)?->isActive());
        self::assertSame(AdministrationBankAccountWriteResult::Success, $this->manager()->setActive($this->admin(self::A), $id, true));
        self::assertTrue($this->repository()->find($this->admin(self::A), $id)?->isActive());
        self::assertFalse(method_exists($this->repository(), 'delete'));
    }

    public function test_configuration_missing_success_filters_and_invalid_reference_without_partial_update(): void
    {
        [, $id] = $this->manager()->create($this->admin(self::A), new Iban('NL91ABNA0417164300'), null, new AccountName('Holder'), new BankAccountLabel('Main'));
        self::assertSame(BankingPostingConfigurationReadStatus::Missing, $this->reader()->read($this->admin(self::A), $id)->status);
        self::assertSame(UpdateBankingPostingConfigurationResult::Saved, $this->updater()->execute($this->admin(self::A), $id, $this->journal(1), $this->account(1)));
        self::assertSame(BankingPostingConfigurationReadStatus::Success, $this->reader()->read($this->admin(self::A), $id)->status);
        $settings = $this->app->make(GetBankingSettings::class)->execute($this->admin(self::A));
        self::assertSame(['BANK'], array_map(static fn ($journal) => $journal->code()->value(), $settings->bankJournals));
        self::assertSame(['BANK'], array_map(static fn ($account) => $account->code()->value(), $settings->bankLedgerAccounts));
        $before = (array) DB::table('banking_posting_configurations')->first();
        foreach ([[$this->journal(2), $this->account(1)], [$this->journal(3), $this->account(1)], [$this->journal(1), $this->account(2)], [$this->journal(1), $this->account(3)], [$this->journal(4), $this->account(1)], [$this->journal(1), $this->account(4)]] as [$journal, $ledger]) {
            self::assertSame(UpdateBankingPostingConfigurationResult::InvalidReference, $this->updater()->execute($this->admin(self::A), $id, $journal, $ledger));
            self::assertSame($before, (array) DB::table('banking_posting_configurations')->first());
        }
        $this->manager()->setActive($this->admin(self::A), $id, false);
        $invalid = $this->reader()->read($this->admin(self::A), $id);
        self::assertSame(BankingPostingConfigurationReadStatus::InvalidReference, $invalid->status);
        self::assertSame([BankingPostingConfigurationInvalidReference::BankAccount], $invalid->invalidReferences);
        self::assertSame([], array_intersect(['accounts_receivable_ledger_account_id', 'accounts_payable_ledger_account_id'], array_keys($before)));
    }

    public function test_non_eur_operational_account_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        new AdministrationBankAccount(new AdministrationBankAccountId(new Uuid('b2200000-0000-4000-8000-000000000001')), $this->admin(self::A), new Iban('NL91ABNA0417164300'), null, new AccountName('Holder'), new BankAccountLabel('Main'), new Currency('USD'), AdministrationBankAccountStatus::Active);
    }

    private function journalRow(string $admin, int $n, string $code, string $type, string $status): array
    {
        return ['id' => $this->journal($n)->toString(), 'administration_id' => $admin, 'code' => $code, 'name' => $code, 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function accountRow(string $admin, int $n, string $code, string $type, string $status): array
    {
        return ['id' => $this->account($n)->toString(), 'administration_id' => $admin, 'code' => $code, 'name' => $code, 'type' => $type, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function journal(int $n): JournalId
    {
        return new JournalId(new Uuid(sprintf('b2300000-0000-4000-8000-%012d', $n)));
    }

    private function account(int $n): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid(sprintf('b2400000-0000-4000-8000-%012d', $n)));
    }

    private function manager(): ManageAdministrationBankAccounts
    {
        return $this->app->make(ManageAdministrationBankAccounts::class);
    }

    private function repository(): AdministrationBankAccountRepository
    {
        return $this->app->make(AdministrationBankAccountRepository::class);
    }

    private function updater(): UpdateBankingPostingConfiguration
    {
        return $this->app->make(UpdateBankingPostingConfiguration::class);
    }

    private function reader(): BankingPostingConfigurationReader
    {
        return $this->app->make(BankingPostingConfigurationReader::class);
    }
}

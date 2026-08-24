<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AdministrationRole;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SalesPostingConfigurationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = '71000000-0000-4000-8000-000000000001';

    private const string A = '71000000-0000-4000-8000-000000000002';

    private const string B = '72000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP = '71000000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity();
        $this->login();
    }

    public function test_empty_masterdata_is_explained_without_a_creation_or_development_shortcut(): void
    {
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Verkoopboekingen')
            ->assertSee('Niet ingesteld')
            ->assertSee('Er zijn nog geen geschikte dagboeken/grootboekrekeningen beschikbaar.')
            ->assertDontSee('development:provision-demo-accounting')
            ->assertSee('disabled', false);
    }

    public function test_provisioned_configuration_and_safe_same_tenant_options_are_displayed_and_saved(): void
    {
        $masterData = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::A));
        (new EloquentLedgerAccountRepository)->save($this->administration(self::A), new LedgerAccount(
            new LedgerAccountId(new Uuid('73000000-0000-4000-8000-000000000001')),
            new LedgerAccountCode('1310'),
            new LedgerAccountName('<script>alert(1)</script>'),
            LedgerAccountType::Asset,
            LedgerAccountStatus::Active,
        ));
        $other = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::B));

        $this->get('/settings/administration')->assertOk()
            ->assertSee('Geldig')
            ->assertSee('VERK – Verkoop')
            ->assertSee('1300 – Debiteuren')
            ->assertSee('8000 – Omzet')
            ->assertSee('1600 – Af te dragen btw')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($other->salesJournal->id()->toString());

        $this->put('/settings/administration/sales-posting', [
            'administration_id' => self::B,
            'sales_journal_id' => $masterData->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect(route('settings.administration.edit'))->assertSessionHas('status', 'Verkoopboekingsinstellingen opgeslagen.');

        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::B)->count());
    }

    public function test_malformed_cross_tenant_and_inactive_references_are_safe_and_preserve_old_configuration(): void
    {
        $masterData = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::A));
        $other = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::B));
        $before = (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first();

        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => 'not-a-uuid',
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_journal_id');

        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => $other->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_posting');

        DB::table('ledger_accounts')->where('id', $masterData->revenue->id()->toString())->update(['status' => 'inactive']);
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Ongeldig – aandacht vereist')
            ->assertSee('Huidige omzetrekening')
            ->assertSee('8000 – Omzet');
        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => $masterData->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_posting');

        self::assertSame($before, (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first());
    }

    private function identity(): void
    {
        $userId = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($userId, new DisplayName('Settings Manager'), new EmailAddress('posting-settings@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administrationEntity(self::A, 'POSTA'));
        $administrations->save($this->administrationEntity(self::B, 'POSTB'));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), $userId, $this->administration(self::A), true,
            new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'),
        ));
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(
            new MembershipRoleId(new Uuid('71000000-0000-4000-8000-000000000004')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), AdministrationRole::Manager->id(), true,
        ));
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'posting-settings@example.com', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::A]);
    }

    private function administrationEntity(string $id, string $code): Administration
    {
        return new Administration($this->administration($id), new AdministrationCode($code), new AdministrationName('Administration '.$code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxPostingRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActiveAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_shows_only_active_accessible_administrations(): void
    {
        [$userId, $accessible, $inaccessible] = $this->setupContext();
        $inactive = $this->administration('04', 'INACTIVE', 'Inactive Administration', AdministrationStatus::Inactive);
        (new EloquentAdministrationRepository)->save($inactive);
        (new EloquentAdministrationMembershipRepository)->save($this->membership('14', $userId, $inactive->id()));
        $this->login();

        $this->get('/administrations/select')->assertOk()
            ->assertSee($accessible->name()->toString())
            ->assertDontSee($inaccessible->name()->toString())
            ->assertDontSee($inactive->name()->toString());
    }

    public function test_valid_selection_sets_session_and_exposes_request_context(): void
    {
        [, $administration] = $this->setupContext();
        $this->seedDashboard($administration, 1, '100', '60', '40', '12');
        $this->login();

        $this->post('/administrations/select', ['administration_id' => $administration->id()->toString()])
            ->assertRedirect('/app')
            ->assertSessionHas(EnsureActiveAdministration::SESSION_KEY, $administration->id()->toString());
        $this->get('/app')->assertOk()
            ->assertSee($administration->name()->toString())
            ->assertSee('Dashboard')
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-controls="primary-navigation"', false)
            ->assertSee('Administratie wisselen')
            ->assertSee('Uitloggen')
            ->assertSee('EUR 100,00')
            ->assertSee('EUR 60,00')
            ->assertSee('EUR 40,00')
            ->assertSee('EUR 12,00')
            ->assertSee('Periode:')
            ->assertDontSee('Nog geen data gekoppeld')
            ->assertSee('Rapportages')
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('%');
    }

    public function test_unauthorized_or_invalid_selection_never_replaces_context(): void
    {
        [, $accessible, $inaccessible] = $this->setupContext();
        $this->login();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $accessible->id()->toString()]);

        $this->post('/administrations/select', ['administration_id' => $inaccessible->id()->toString()])
            ->assertSessionHasErrors('administration_id')
            ->assertSessionHas(EnsureActiveAdministration::SESSION_KEY, $accessible->id()->toString());
        $this->post('/administrations/select', ['administration_id' => 'invalid'])
            ->assertSessionHasErrors('administration_id')
            ->assertSessionHas(EnsureActiveAdministration::SESSION_KEY, $accessible->id()->toString());
    }

    public function test_invalid_session_revocation_and_inactive_administration_clear_context(): void
    {
        [$userId, $administration] = $this->setupContext();
        $this->login();
        $membershipRepository = new EloquentAdministrationMembershipRepository;
        $membership = $membershipRepository->findByUserAndAdministration($userId, $administration->id());

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => 'invalid'])
            ->get('/app')->assertRedirect('/administrations/select')->assertSessionMissing(EnsureActiveAdministration::SESSION_KEY);

        $membership->deactivate();
        $membershipRepository->save($membership);
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administration->id()->toString()])
            ->get('/app')->assertRedirect('/administrations/select')->assertSessionMissing(EnsureActiveAdministration::SESSION_KEY);

        $membership->activate();
        $membershipRepository->save($membership);
        $administration->deactivate();
        (new EloquentAdministrationRepository)->save($administration);
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administration->id()->toString()])
            ->get('/app')->assertRedirect('/administrations/select')->assertSessionMissing(EnsureActiveAdministration::SESSION_KEY);
    }

    public function test_switch_and_logout_are_safe(): void
    {
        [$userId, $first] = $this->setupContext();
        $second = $this->administration('05', 'SECOND', 'Second Administration');
        (new EloquentAdministrationRepository)->save($second);
        (new EloquentAdministrationMembershipRepository)->save($this->membership('15', $userId, $second->id()));
        $this->seedDashboard($first, 1, '100', '60', '40', '12');
        $this->seedDashboard($second, 2, '250', '90', '70', '21');
        $this->login();

        $this->post('/administrations/select', ['administration_id' => $first->id()->toString()]);
        $this->get('/app')->assertOk()->assertSee('EUR 100,00')->assertDontSee('EUR 250,00');
        $this->post('/administrations/select', ['administration_id' => $second->id()->toString()])
            ->assertSessionHas(EnsureActiveAdministration::SESSION_KEY, $second->id()->toString());
        $this->get('/app')->assertOk()->assertSee('EUR 250,00')->assertDontSee('EUR 100,00');
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing(EnsureActiveAdministration::SESSION_KEY);
        $this->get('/app')->assertRedirect('/login');
    }

    /** @return array{UserId, Administration, Administration} */
    private function setupContext(): array
    {
        $userId = new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));
        $this->app->make(ProvisionUserAccount::class)->execute($userId, new DisplayName('Tenant User'), new EmailAddress('tenant@example.com'), 'correct-secure-password');
        $accessible = $this->administration('02', 'ACCESS', 'Accessible Administration');
        $inaccessible = $this->administration('03', 'DENIED', 'Denied Administration');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($accessible);
        $administrations->save($inaccessible);
        (new EloquentAdministrationMembershipRepository)->save($this->membership('12', $userId, $accessible->id()));

        return [$userId, $accessible, $inaccessible];
    }

    private function administration(string $suffix, string $code, string $name, AdministrationStatus $status = AdministrationStatus::Active): Administration
    {
        return new Administration(new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)), new AdministrationCode($code), new AdministrationName($name), null, new Currency('EUR'), $status);
    }

    private function membership(string $suffix, UserId $userId, AdministrationId $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-4466554400'.$suffix)), $userId, $administrationId, true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'tenant@example.com', 'password' => 'correct-secure-password'])->assertRedirect('/app');
    }

    private function seedDashboard(
        Administration $administration,
        int $sequence,
        string $revenue,
        string $receivable,
        string $payable,
        string $vat,
    ): void {
        $prefix = (string) $sequence;
        $assetId = $prefix.'1000000-0000-4000-8000-000000000001';
        $revenueId = $prefix.'2000000-0000-4000-8000-000000000001';
        $entryId = $prefix.'3000000-0000-4000-8000-000000000001';
        $baseLineId = $prefix.'4000000-0000-4000-8000-000000000001';
        $taxLineId = $prefix.'5000000-0000-4000-8000-000000000001';
        $relationId = $prefix.'6000000-0000-4000-8000-000000000001';
        $date = (new DateTimeImmutable('today'))->format('Y-m-d');

        foreach ([
            [$assetId, '1000', 'Asset', 'asset'],
            [$revenueId, '8000', 'Revenue', 'revenue'],
        ] as [$id, $code, $name, $type]) {
            LedgerAccountRecord::query()->create([
                'id' => $id,
                'administration_id' => $administration->id()->toString(),
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'status' => 'active',
            ]);
        }

        JournalRecord::query()->create([
            'id' => $prefix.'7000000-0000-4000-8000-000000000001',
            'administration_id' => $administration->id()->toString(),
            'code' => 'DASH'.$sequence,
            'name' => 'Dashboard test journal '.$sequence,
            'type' => 'general',
            'status' => 'active',
        ]);
        JournalEntryRecord::query()->create([
            'id' => $entryId,
            'administration_id' => $administration->id()->toString(),
            'journal_id' => $prefix.'7000000-0000-4000-8000-000000000001',
            'posting_date' => $date,
            'reference' => 'Dashboard '.$sequence,
            'status' => 'posted',
        ]);
        JournalEntryLineRecord::query()->create([
            'id' => $baseLineId,
            'administration_id' => $administration->id()->toString(),
            'journal_entry_id' => $entryId,
            'ledger_account_id' => $assetId,
            'debit_amount' => $revenue,
            'credit_amount' => null,
            'currency' => 'EUR',
            'description' => 'Dashboard debit',
        ]);
        JournalEntryLineRecord::query()->create([
            'id' => $taxLineId,
            'administration_id' => $administration->id()->toString(),
            'journal_entry_id' => $entryId,
            'ledger_account_id' => $revenueId,
            'debit_amount' => null,
            'credit_amount' => $revenue,
            'currency' => 'EUR',
            'description' => 'Dashboard revenue',
        ]);
        RelationRecord::query()->create([
            'id' => $relationId,
            'administration_id' => $administration->id()->toString(),
            'code' => 'REL-'.$sequence,
            'display_name' => 'Dashboard relation '.$sequence,
            'active' => true,
        ]);

        foreach ([['8', 'receivable', $receivable], ['9', 'payable', $payable]] as [$idPrefix, $type, $amount]) {
            OpenItemRecord::query()->create([
                'id' => $prefix.$idPrefix.'000000-0000-4000-8000-000000000001',
                'administration_id' => $administration->id()->toString(),
                'relation_id' => $relationId,
                'journal_entry_id' => $entryId,
                'open_item_type' => $type,
                'side' => $type === 'receivable' ? 'debit' : 'credit',
                'original_amount' => $amount,
                'currency' => 'EUR',
                'opened_on' => $date,
            ]);
        }

        TaxPostingRecord::query()->create([
            'id' => $prefix.'a000000-0000-4000-8000-000000000001',
            'administration_id' => $administration->id()->toString(),
            'tax_code_id' => $prefix.'b000000-0000-4000-8000-000000000001',
            'tax_rate' => '21',
            'treatment' => 'domestic_standard',
            'vat_return_classification' => 'domestic_standard',
            'icp_classification' => 'none',
            'taxable_base' => $revenue,
            'tax_amount' => $vat,
            'currency' => 'EUR',
            'direction' => 'output',
            'type' => 'original',
            'source_document_type' => 'sales_invoice',
            'source_document_id' => $prefix.'c000000-0000-4000-8000-000000000001',
            'source_line_id' => $prefix.'d000000-0000-4000-8000-000000000001',
            'posting_date' => $date,
            'journal_entry_id' => $entryId,
            'base_journal_entry_line_id' => $baseLineId,
            'tax_journal_entry_line_id' => $taxLineId,
            'reversed_tax_posting_id' => null,
        ]);
    }
}

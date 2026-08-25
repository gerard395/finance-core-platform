<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Fiscal\TaxCodeCatalogueProvisioningConflict;
use App\Application\Fiscal\TaxCodeReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Fiscal\DutchTaxCodeCatalogueProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DutchTaxCodeCatalogueProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMIN_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdministration(self::ADMIN_A, 'NL-A');
        $this->createAdministration(self::ADMIN_B, 'NL-B');
    }

    public function test_empty_administration_receives_exact_active_dutch_output_catalogue(): void
    {
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));

        self::assertSame([
            ['code' => 'BTW0', 'name' => 'BTW 0%', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'zero_rated', 'vat_return_classification' => 'domestic_zero_rated', 'icp_classification' => 'none'],
            ['code' => 'BTW21', 'name' => 'BTW hoog (algemeen tarief)', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none'],
            ['code' => 'BTW9', 'name' => 'BTW laag (verlaagd tarief)', 'rate' => '9', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_reduced', 'vat_return_classification' => 'domestic_reduced', 'icp_classification' => 'none'],
            ['code' => 'BUITENSCOPE', 'name' => 'Buiten Nederlandse btw-heffing', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'outside_scope', 'vat_return_classification' => 'outside_scope', 'icp_classification' => 'none'],
            ['code' => 'EUDIENST', 'name' => 'Btw verlegd - dienst EU', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service'],
            ['code' => 'ICLGOEDEREN', 'name' => 'Intracommunautaire levering goederen', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'intra_community_goods', 'vat_return_classification' => 'intra_community_supplies', 'icp_classification' => 'goods_supply'],
            ['code' => 'VRIJGESTELD', 'name' => 'Vrijgesteld', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'exempt', 'vat_return_classification' => 'exempt', 'icp_classification' => 'none'],
        ], DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->orderBy('code')->get(['code', 'name', 'rate', 'direction', 'status', 'treatment', 'vat_return_classification', 'icp_classification'])->map(static fn (object $row): array => (array) $row)->all());
        self::assertSame(0, DB::table('tax_codes')->where('administration_id', self::ADMIN_B)->count());
    }

    public function test_empty_administration_receives_exact_deterministic_domestic_input_catalogue_without_output_mutation(): void
    {
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
        $outputBefore = DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('direction', 'output')->orderBy('id')->get()->toArray();

        $this->provisioner()->ensureDutchBasicInputForAdministration($this->administration(self::ADMIN_A));
        $first = DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('direction', 'input')->orderBy('code')->get(['id', 'code', 'name', 'rate', 'direction', 'treatment', 'vat_return_classification', 'icp_classification'])->map(static fn (object $row): array => (array) $row)->all();
        $this->provisioner()->ensureDutchBasicInputForAdministration($this->administration(self::ADMIN_A));

        self::assertSame(['INBTW0', 'INBTW21', 'INBTW9', 'INBUITEN', 'INVRIJ'], array_column($first, 'code'));
        self::assertSame(['zero_rated', 'domestic_standard', 'domestic_reduced', 'outside_scope', 'exempt'], array_column($first, 'treatment'));
        self::assertSame(['domestic_zero_rated', 'domestic_standard', 'domestic_reduced', 'outside_scope', 'exempt'], array_column($first, 'vat_return_classification'));
        self::assertSame(['input'], array_values(array_unique(array_column($first, 'direction'))));
        self::assertSame($first, DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('direction', 'input')->orderBy('code')->get(['id', 'code', 'name', 'rate', 'direction', 'treatment', 'vat_return_classification', 'icp_classification'])->map(static fn (object $row): array => (array) $row)->all());
        self::assertEquals($outputBefore, DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('direction', 'output')->orderBy('id')->get()->toArray());
        self::assertSame(0, DB::table('tax_codes')->where('administration_id', self::ADMIN_B)->count());
    }

    public function test_second_run_preserves_rows_timestamps_names_and_inactive_state(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 10:00:00');
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
        DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('code', 'BTW21')->update([
            'name' => 'Eigen naam hoog',
            'status' => 'inactive',
        ]);
        $before = DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->orderBy('code')->get()->map(static fn (object $row): array => (array) $row)->all();

        CarbonImmutable::setTestNow('2026-08-25 10:00:00');
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));

        self::assertSame($before, DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->orderBy('code')->get()->map(static fn (object $row): array => (array) $row)->all());
        self::assertCount(7, $before);
    }

    public function test_compatible_custom_code_is_preserved_and_only_missing_codes_are_created(): void
    {
        $createdAt = '2026-01-01 12:00:00';
        DB::table('tax_codes')->insert([
            'id' => '30000000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN_A,
            'code' => 'BTW9', 'name' => 'Mijn lage tarief', 'rate' => '9.0000', 'direction' => 'output',
            'status' => 'inactive', 'created_at' => $createdAt, 'updated_at' => $createdAt,
            'treatment' => 'domestic_reduced', 'vat_return_classification' => 'domestic_reduced', 'icp_classification' => 'none',
        ]);

        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));

        $this->assertDatabaseHas('tax_codes', ['id' => '30000000-0000-4000-8000-000000000001', 'name' => 'Mijn lage tarief', 'rate' => '9.0000', 'status' => 'inactive', 'updated_at' => $createdAt]);
        self::assertSame(7, DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->count());
    }

    public function test_incompatible_rate_or_direction_fails_atomically_with_typed_conflict(): void
    {
        DB::table('tax_codes')->insert([
            'id' => '30000000-0000-4000-8000-000000000002', 'administration_id' => self::ADMIN_A,
            'code' => 'BTW9', 'name' => 'Conflict', 'rate' => '8', 'direction' => 'output',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            'treatment' => 'domestic_reduced', 'vat_return_classification' => 'domestic_reduced', 'icp_classification' => 'none',
        ]);

        try {
            $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
            self::fail('An incompatible catalogue collision must stop provisioning.');
        } catch (TaxCodeCatalogueProvisioningConflict $exception) {
            self::assertStringContainsString('BTW9', $exception->getMessage());
        }

        self::assertSame(['BTW9'], DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->pluck('code')->all());
    }

    public function test_incompatible_reporting_classification_is_a_typed_conflict_and_is_never_reset(): void
    {
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
        DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('code', 'BTW0')->update([
            'treatment' => 'exempt', 'vat_return_classification' => 'exempt',
        ]);

        $this->expectException(TaxCodeCatalogueProvisioningConflict::class);
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
    }

    public function test_sales_selection_contract_exposes_only_active_same_tenant_output_codes(): void
    {
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));
        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_B));
        DB::table('tax_codes')->where('administration_id', self::ADMIN_A)->where('code', 'BTW0')->update(['status' => 'inactive']);
        DB::table('tax_codes')->insert([
            'id' => '30000000-0000-4000-8000-000000000003', 'administration_id' => self::ADMIN_A,
            'code' => 'INPUT21', 'name' => 'Input 21', 'rate' => '21', 'direction' => 'input',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none',
        ]);

        $items = $this->app->make(TaxCodeReadRepository::class)->findActiveForAdministrationAndDirection(
            $this->administration(self::ADMIN_A),
            TaxPostingDirection::Output,
        );

        self::assertSame(['BTW21', 'BTW9', 'BUITENSCOPE', 'EUDIENST', 'ICLGOEDEREN', 'VRIJGESTELD'], array_map(static fn ($item): string => $item->code()->value(), $items));
    }

    public function test_provisioning_does_not_write_tax_posting_snapshot_truth(): void
    {
        $before = DB::table('tax_postings')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();

        $this->provisioner()->ensureDutchBasicOutputForAdministration($this->administration(self::ADMIN_A));

        self::assertSame($before, DB::table('tax_postings')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all());
    }

    public function test_contract_is_bound_to_the_dutch_catalogue_adapter(): void
    {
        self::assertInstanceOf(DutchTaxCodeCatalogueProvisioner::class, $this->provisioner());
    }

    private function provisioner(): TaxCodeCatalogueProvisioner
    {
        return $this->app->make(TaxCodeCatalogueProvisioner::class);
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
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
}

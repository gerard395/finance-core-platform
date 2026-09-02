<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Application\Fiscal\TaxTreatmentDefinitionSelectionStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxTreatmentDefinitionRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EloquentTaxTreatmentDefinitionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const A = '91000000-0000-4000-8000-000000000001';

    private const B = '92000000-0000-4000-8000-000000000001';

    private const CODE_A = '93000000-0000-4000-8000-000000000001';

    private const CODE_B = '93000000-0000-4000-8000-000000000002';

    private const DEFINITION = '94000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([[self::A, 'A', self::CODE_A], [self::B, 'B', self::CODE_B]] as [$id, $code, $taxCodeId]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tax_codes')->insert([
                'id' => $taxCodeId, 'administration_id' => $id, 'code' => 'IPV21', 'name' => 'IPV 21', 'rate' => '21',
                'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard',
                'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_versions_roundtrip_without_mutating_history_and_reads_are_tenant_scoped(): void
    {
        $repository = new EloquentTaxTreatmentDefinitionRepository;
        $repository->append($this->definition(self::A, 1, '21', true));
        $repository->append($this->definition(self::A, 2, '9', true));

        self::assertSame('21', $repository->findVersion($this->admin(self::A), $this->definitionId(), 1)?->vatRate()->value());
        self::assertSame('9', $repository->findVersion($this->admin(self::A), $this->definitionId(), 2)?->vatRate()->value());
        self::assertNull($repository->findActiveForTaxCode($this->admin(self::A), $this->taxCodeId()));
        self::assertNull($repository->findVersion($this->admin(self::B), $this->definitionId(), 1));
        self::assertInstanceOf(EloquentTaxTreatmentDefinitionRepository::class, $this->app->make(TaxTreatmentDefinitionRepository::class));
        self::assertSame(TaxTreatmentDefinitionSelectionStatus::IntegrityFailure, $repository->resolveActiveForTaxCode($this->admin(self::A), $this->taxCodeId())->status);
    }

    public function test_selection_requires_exactly_one_active_definition(): void
    {
        $repository = new EloquentTaxTreatmentDefinitionRepository;
        self::assertSame(TaxTreatmentDefinitionSelectionStatus::Missing, $repository->resolveActiveForTaxCode($this->admin(self::A), $this->taxCodeId())->status);
        $repository->append($this->definition(self::A, 1, '21', true));
        self::assertSame(TaxTreatmentDefinitionSelectionStatus::Found, $repository->resolveActiveForTaxCode($this->admin(self::A), $this->taxCodeId())->status);
        self::assertSame(TaxTreatmentDefinitionSelectionStatus::Missing, $repository->resolveActiveForTaxCode($this->admin(self::B), $this->taxCodeId())->status);
    }

    public function test_database_rejects_cross_tenant_tax_code_association(): void
    {
        $this->expectException(QueryException::class);
        DB::table('tax_treatment_definitions')->insert([
            'id' => self::DEFINITION, 'administration_id' => self::A, 'tax_code_id' => self::CODE_B, 'version' => 1,
            'treatment_type' => 'eu_b2b_general_rule_service', 'jurisdiction' => 'NL', 'vat_rate' => '21',
            'supplier_vat_mode' => 'self_assessed', 'deductibility_policy' => 'user_specified_line_rate',
            'leg_definitions' => '[]', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function definition(string $administration, int $version, string $rate, bool $active): TaxTreatmentDefinition
    {
        return new TaxTreatmentDefinition(
            $this->definitionId(), $this->admin($administration), $this->taxCodeId(), $version,
            TaxTreatmentType::EuB2bGeneralRuleService, 'NL', new TaxRate($rate), SupplierVatMode::SelfAssessed,
            DeductibilityPolicy::UserSpecifiedLineRate,
            [
                new TaxLegDefinition(TaxLegRole::VatPayable, TaxPostingDirection::Output, TaxReportingClassification::EuGeneralServiceDue4b, TaxLedgerAccountRole::VatPayableControl),
                new TaxLegDefinition(TaxLegRole::VatDeductible, TaxPostingDirection::Input, TaxReportingClassification::DeductibleInput5b, TaxLedgerAccountRole::InputVatControl),
            ],
            $active,
        );
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function definitionId(): TaxTreatmentDefinitionId
    {
        return new TaxTreatmentDefinitionId(new Uuid(self::DEFINITION));
    }

    private function taxCodeId(): TaxCodeId
    {
        return new TaxCodeId(new Uuid(self::CODE_A));
    }
}

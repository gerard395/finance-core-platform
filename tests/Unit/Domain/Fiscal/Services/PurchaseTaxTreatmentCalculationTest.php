<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Services;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\Services\PurchaseTaxTreatmentCalculation;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxLegId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PurchaseTaxTreatmentCalculationTest extends TestCase
{
    #[DataProvider('reverseChargeCases')]
    public function test_reverse_charge_calculates_exact_supplier_and_multi_leg_truth(
        int $basisPoints,
        string $deductible,
        string $nonDeductible,
        int $expectedLegs,
    ): void {
        $result = (new PurchaseTaxTreatmentCalculation)->calculate(
            $this->definition(SupplierVatMode::SelfAssessed),
            $this->groupId(),
            $this->legIds(),
            $this->money('100'),
            $this->money('0'),
            new DeductibilityBasisPoints($basisPoints),
        );

        self::assertSame('100', $result->amounts->supplierGross->amount());
        self::assertSame('21', $result->amounts->selfAssessedVat->amount());
        self::assertSame($deductible, $result->amounts->deductibleVat->amount());
        self::assertSame($nonDeductible, $result->amounts->nonDeductibleTaxCost->amount());
        self::assertCount($expectedLegs, $result->legs);
        self::assertSame(TaxLegRole::VatPayable, $result->legs[0]->role);
        if ($expectedLegs === 2) {
            self::assertSame(TaxLegRole::VatDeductible, $result->legs[1]->role);
        }
        self::assertTrue($result->amounts->selfAssessedVat->equals(
            $result->amounts->deductibleVat->add($result->amounts->nonDeductibleTaxCost),
        ));
    }

    /** @return iterable<string, array{int, string, string, int}> */
    public static function reverseChargeCases(): iterable
    {
        yield 'full deduction' => [10000, '21', '0', 2];
        yield 'zero deduction' => [0, '0', '21', 1];
        yield 'half deduction' => [5000, '10.5', '10.5', 2];
    }

    public function test_domestic_supplier_vat_preserves_payable_and_has_no_self_assessed_payable_leg(): void
    {
        $definition = $this->definition(SupplierVatMode::SupplierCharged, [
            new TaxLegDefinition(TaxLegRole::VatDeductible, TaxPostingDirection::Input, TaxReportingClassification::DomesticInput, TaxLedgerAccountRole::InputVatControl),
        ]);
        $result = (new PurchaseTaxTreatmentCalculation)->calculate(
            $definition,
            $this->groupId(),
            $this->legIds(),
            $this->money('100'),
            $this->money('21'),
            new DeductibilityBasisPoints(10000),
        );

        self::assertSame('121', $result->amounts->supplierGross->amount());
        self::assertSame('0', $result->amounts->selfAssessedVat->amount());
        self::assertSame('21', $result->amounts->assessedVat()->amount());
        self::assertSame('21', $result->amounts->deductibleVat->amount());
        self::assertSame('0', $result->amounts->nonDeductibleTaxCost->amount());
        self::assertCount(1, $result->legs);
        self::assertSame(TaxLegRole::VatDeductible, $result->legs[0]->role);
    }

    public function test_zero_classification_can_emit_a_zero_deductible_leg_without_journal_amount(): void
    {
        $definition = $this->definition(SupplierVatMode::SupplierCharged, [
            new TaxLegDefinition(TaxLegRole::VatDeductible, TaxPostingDirection::Input, TaxReportingClassification::ZeroRated, TaxLedgerAccountRole::InputVatControl, true),
        ], '0');
        $result = (new PurchaseTaxTreatmentCalculation)->calculate(
            $definition, $this->groupId(), $this->legIds(), $this->money('100'), $this->money('0'), new DeductibilityBasisPoints(10000),
        );

        self::assertCount(1, $result->legs);
        self::assertSame('0', $result->legs[0]->amount->amount());
    }

    #[DataProvider('zeroTreatmentCases')]
    public function test_zero_exempt_and_outside_scope_remain_distinct_reporting_truth(
        TaxTreatmentType $type,
        TaxReportingClassification $classification,
    ): void {
        $definition = $this->definition(SupplierVatMode::SupplierCharged, [
            new TaxLegDefinition(TaxLegRole::VatDeductible, TaxPostingDirection::Input, $classification, TaxLedgerAccountRole::InputVatControl, true),
        ], '0', $type);

        $result = (new PurchaseTaxTreatmentCalculation)->calculate(
            $definition, $this->groupId(), $this->legIds(), $this->money('100'), $this->money('0'), new DeductibilityBasisPoints(10000),
        );

        self::assertSame($type, $result->snapshot->treatmentType);
        self::assertSame($classification, $result->legs[0]->reportingClassification);
        self::assertSame('0', $result->amounts->selfAssessedVat->amount());
        self::assertSame('0', $result->amounts->assessedVat()->amount());
    }

    /** @return iterable<string, array{TaxTreatmentType, TaxReportingClassification}> */
    public static function zeroTreatmentCases(): iterable
    {
        yield 'zero rated' => [TaxTreatmentType::ZeroRated, TaxReportingClassification::ZeroRated];
        yield 'exempt' => [TaxTreatmentType::Exempt, TaxReportingClassification::Exempt];
        yield 'outside scope' => [TaxTreatmentType::OutsideScope, TaxReportingClassification::OutsideScope];
    }

    public function test_rounds_assessed_then_split_half_up_and_assigns_remainder_exactly(): void
    {
        $definition = $this->definition(SupplierVatMode::SelfAssessed, rate: '21.1234');
        $result = (new PurchaseTaxTreatmentCalculation)->calculate(
            $definition, $this->groupId(), $this->legIds(), $this->money('0.12345678'), $this->money('0'), new DeductibilityBasisPoints(3333),
        );

        self::assertSame('0.02607827', $result->amounts->selfAssessedVat->amount());
        self::assertSame('0.00869189', $result->amounts->deductibleVat->amount());
        self::assertSame('0.01738638', $result->amounts->nonDeductibleTaxCost->amount());
        self::assertTrue($result->amounts->selfAssessedVat->equals(
            $result->amounts->deductibleVat->add($result->amounts->nonDeductibleTaxCost),
        ));
    }

    public function test_definition_version_roles_and_deductibility_are_typed(): void
    {
        self::assertSame(1, $this->definition(SupplierVatMode::SelfAssessed)->version());
        self::assertSame('90000000-0000-4000-8000-000000000010', $this->groupId()->toString());
        self::assertSame('1', $this->legIds()[TaxLegRole::VatPayable->value]->taxPostingId()->toString()[-1]);

        $this->expectException(InvalidArgumentException::class);
        new DeductibilityBasisPoints(10001);
    }

    /** @param null|list<TaxLegDefinition> $legs */
    private function definition(
        SupplierVatMode $mode,
        ?array $legs = null,
        string $rate = '21',
        ?TaxTreatmentType $type = null,
    ): TaxTreatmentDefinition {
        $legs ??= [
            new TaxLegDefinition(TaxLegRole::VatPayable, TaxPostingDirection::Output, TaxReportingClassification::EuGeneralServiceDue4b, TaxLedgerAccountRole::VatPayableControl),
            new TaxLegDefinition(TaxLegRole::VatDeductible, TaxPostingDirection::Input, TaxReportingClassification::DeductibleInput5b, TaxLedgerAccountRole::InputVatControl),
        ];

        return new TaxTreatmentDefinition(
            new TaxTreatmentDefinitionId(new Uuid('90000000-0000-4000-8000-000000000001')),
            new AdministrationId(new Uuid('90000000-0000-4000-8000-000000000002')),
            new TaxCodeId(new Uuid('90000000-0000-4000-8000-000000000003')),
            1,
            $type ?? ($mode === SupplierVatMode::SelfAssessed ? TaxTreatmentType::EuB2bGeneralRuleService : TaxTreatmentType::DomesticSupplierVat),
            'NL',
            new TaxRate($rate),
            $mode,
            DeductibilityPolicy::UserSpecifiedLineRate,
            $legs,
        );
    }

    private function groupId(): TaxTreatmentGroupId
    {
        return new TaxTreatmentGroupId(new Uuid('90000000-0000-4000-8000-000000000010'));
    }

    /** @return array<string, TaxLegId> */
    private function legIds(): array
    {
        return [
            TaxLegRole::VatPayable->value => new TaxLegId(new Uuid('90000000-0000-4000-8000-000000000011')),
            TaxLegRole::VatDeductible->value => new TaxLegId(new Uuid('90000000-0000-4000-8000-000000000012')),
        ];
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }
}
